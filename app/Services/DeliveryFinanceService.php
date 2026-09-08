<?php

namespace App\Services;

use App\Models\{CourierBill, CourierBillLine, CourierBillPayment, DeliveryFinanceSetting, DeliveryNote, DriverDailyAllowance, DriverSettlement, DriverSettlementPayment, Expense, ExpenseDeliveryAllocation, Supplier, VehicleExpense, VehicleExpenseAllocation};
use Illuminate\Support\Facades\DB;

/**
 * Every automatically created Expense here reuses the existing
 * expenses.source_type/source_id idempotency pattern (the same one the
 * Staff/Payroll module uses) — a real database unique constraint on
 * (source_type, source_id), not just application-level care.
 */
class DeliveryFinanceService
{
    public function __construct(private NumberingService $numbers) {}

    // ---------------------------------------------------------------
    // Delivery-level profit/loss
    // ---------------------------------------------------------------

    /**
     * Direct Profit/Loss = Customer Delivery Charge − Direct Delivery Cost.
     * For own-company: direct cost = driver fee + allocated phone allowance.
     * For outside/international courier: direct cost = actual cost if known, else the estimate — and the caller is told which, since "estimated" vs "final" must never be presented as the same thing.
     */
    public function directProfitLoss(DeliveryNote $delivery): array
    {
        $charge = (float) $delivery->customer_delivery_charge;

        if ($delivery->delivery_type === 'own_company') {
            $directCost = (float) $delivery->driver_fee + (float) $delivery->allocated_phone_allowance;
            $isFinal = true; // own-company cost is always known immediately, never "estimated"
        } else {
            $hasActual = $delivery->actual_cost !== null;
            $directCost = $hasActual ? (float) $delivery->actual_cost : (float) $delivery->estimated_cost;
            $isFinal = $hasActual;
        }

        return ['profit_loss' => round($charge - $directCost, 2), 'is_final' => $isFinal, 'direct_cost' => $directCost];
    }

    /** Fully Allocated = Direct Profit/Loss − allocated petrol − allocated maintenance. */
    public function fullyAllocatedProfitLoss(DeliveryNote $delivery): float
    {
        $direct = $this->directProfitLoss($delivery)['profit_loss'];
        return round($direct - (float) $delivery->allocated_petrol_cost - (float) $delivery->allocated_maintenance_cost, 2);
    }

    // ---------------------------------------------------------------
    // Supplier matching for courier companies — case/whitespace-insensitive, reused from the Finance Migration pattern.
    // ---------------------------------------------------------------

    public function findOrCreateCourierSupplier(string $rawName): Supplier
    {
        return $this->findOrCreateSupplierByName($rawName, 'delivery_courier');
    }

    /** Case/whitespace-insensitive supplier matching, reused for both courier companies and vehicle-expense suppliers (garages, petrol stations) — never mislabeling one as the other's type. */
    public function findOrCreateSupplierByName(string $rawName, ?string $supplierType = null): Supplier
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $rawName)));
        $existing = Supplier::all()->first(fn ($s) => strtolower(trim(preg_replace('/\s+/', ' ', $s->name))) === $normalized);
        if ($existing) {
            if ($supplierType && !$existing->supplier_type) {
                $existing->update(['supplier_type' => $supplierType]);
            }
            return $existing;
        }
        return Supplier::create(['supplier_code' => 'SUP-'.str_pad((string) (Supplier::max('id') + 1), 5, '0', STR_PAD_LEFT), 'name' => trim($rawName), 'status' => 'active', 'supplier_type' => $supplierType]);
    }

    // ---------------------------------------------------------------
    // Courier Bills — one bill, many delivery lines, one Expense.
    // ---------------------------------------------------------------

    /** @param array $deliveryLines [['delivery_note_id'=>int,'actual_billed_cost'=>float], ...] */
    public function createCourierBill(array $billData, array $deliveryLines, int $userId): CourierBill
    {
        return DB::transaction(function () use ($billData, $deliveryLines, $userId) {
            // A delivery can only ever appear on one bill — enforced by the DB unique constraint too, not just this check.
            $alreadyBilled = CourierBillLine::whereIn('delivery_note_id', array_column($deliveryLines, 'delivery_note_id'))->pluck('delivery_note_id');
            if ($alreadyBilled->isNotEmpty()) {
                throw new \RuntimeException('One or more deliveries are already on another courier bill: '.$alreadyBilled->implode(', '));
            }

            $bill = CourierBill::create($billData + ['bill_number' => $this->numbers->next('courier_bill'), 'created_by' => $userId]);

            foreach ($deliveryLines as $line) {
                $delivery = DeliveryNote::findOrFail($line['delivery_note_id']);
                CourierBillLine::create([
                    'courier_bill_id' => $bill->id, 'delivery_note_id' => $delivery->id,
                    'estimated_cost' => (float) $delivery->estimated_cost, 'actual_billed_cost' => (float) $line['actual_billed_cost'],
                ]);
                // The estimate in profitability reporting is replaced by the real allocated bill amount.
                $delivery->update(['actual_cost' => (float) $line['actual_billed_cost'], 'courier_bill_id' => $bill->id]);
            }

            return $bill->fresh('lines');
        });
    }

    /**
     * Records one payment against a courier bill. Idempotent via a durable
     * key: a repeated request with the same key (e.g. a network retry or a
     * double-submitted form) is a no-op, not a second payment — this is
     * the actual fix for risk #2, not just relying on the Expense's own
     * source_type+source_id uniqueness, which only stops a duplicate
     * Expense record, not a duplicate amount_paid increment.
     *
     * The linked Expense reflects the CUMULATIVE amount actually paid so
     * far (fixing risk #3) — a partial payment on a large bill posts only
     * that partial amount, never the full bill total, since Expense in
     * this system is a cash-basis record.
     */
    public function payCourierBill(CourierBill $bill, float $amount, array $paymentDetails, string $idempotencyKey, string $proofPath, ?string $proofOriginalName, int $userId): CourierBillPayment
    {
        return DB::transaction(function () use ($bill, $amount, $paymentDetails, $idempotencyKey, $proofPath, $proofOriginalName, $userId) {
            $existing = CourierBillPayment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing; // Genuinely the same request repeated — not a new payment.
            }

            $bill = CourierBill::lockForUpdate()->findOrFail($bill->id); // prevents two concurrent payments from racing on the same bill

            $payment = CourierBillPayment::create([
                'courier_bill_id' => $bill->id, 'amount' => $amount,
                'payment_date' => $paymentDetails['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentDetails['payment_method'] ?? 'bank',
                'payment_reference' => $paymentDetails['payment_reference'] ?? null,
                'proof_path' => $proofPath, 'proof_original_name' => $proofOriginalName,
                'idempotency_key' => $idempotencyKey, 'created_by' => $userId,
            ]);

            $totalPaid = round((float) CourierBillPayment::where('courier_bill_id', $bill->id)->sum('amount'), 2);
            $remaining = round((float) $bill->total_amount - $totalPaid, 2);
            $bill->update([
                'amount_paid' => $totalPaid, 'status' => $remaining <= 0 ? 'paid' : 'partially_paid',
                'payment_date' => $payment->payment_date, 'payment_method' => $payment->payment_method, 'payment_reference' => $payment->payment_reference,
            ]);

            $this->syncExpense('courier_bill', $bill->id, [
                'expense_date' => $payment->payment_date, 'category' => $bill->deliveries()->first()?->delivery_type === 'international_courier' ? 'International Courier Expense' : 'Domestic Courier Expense',
                'payee' => $bill->supplier->name, 'payment_method' => $payment->payment_method,
                'amount_ex_tax' => $totalPaid, 'tax_amount' => 0, 'total_amount' => $totalPaid,
                'reference' => $bill->supplier_invoice_number,
                'description' => "Courier bill {$bill->bill_number} — {$bill->supplier->name} ({$bill->period_start->format('d M')}–{$bill->period_end->format('d M Y')}) — paid to date",
            ]);

            return $payment;
        });
    }

    // ---------------------------------------------------------------
    // Own-company delivery completion — driver fee + daily allowance, split correctly.
    // ---------------------------------------------------------------

    /** Applies the driver fee for one completed delivery. Cancelled/failed deliveries never earn a fee unless explicitly overridden. */
    public function completeOwnDelivery(DeliveryNote $delivery, \Carbon\Carbon $date): void
    {
        DB::transaction(function () use ($delivery, $date) {
            $fee = DeliveryFinanceSetting::valueOn('own_driver_fee', $date);
            $delivery->update(['driver_fee' => $fee]);
            $this->recalculateDailyAllowanceForDriver($delivery->driver_id, $date);
        });
    }

    /**
     * The AED 5 allowance is created at most once per driver+date (a real
     * unique DB constraint enforces this, not just this check), then
     * divided evenly across that driver's completed own-company
     * deliveries for the day — e.g. 5 deliveries → AED 1 allocated each,
     * while the real accounting Expense (created when the settlement is
     * paid) stays a single AED 5, never five separate AED 5 expenses.
     */
    public function recalculateDailyAllowanceForDriver(int $driverId, \Carbon\Carbon $date): void
    {
        // Grouped by ACTUAL completion (delivered_at), never the scheduled
        // delivery_date — a delivery scheduled for one day but genuinely
        // completed on another must count toward the day it actually
        // happened, for both the allowance and driver settlement math.
        $deliveries = DeliveryNote::where('driver_id', $driverId)->where('delivery_type', 'own_company')
            ->whereDate('delivered_at', $date)->where('status', 'delivered')->get();

        if ($deliveries->isEmpty()) {
            DriverDailyAllowance::where('driver_id', $driverId)->whereDate('allowance_date', $date)->delete();
            return;
        }

        $allowanceAmount = DeliveryFinanceSetting::valueOn('own_driver_daily_allowance', $date);
        $existingAllowance = DriverDailyAllowance::where('driver_id', $driverId)->whereDate('allowance_date', $date)->first();
        if ($existingAllowance) {
            $existingAllowance->update(['amount' => $allowanceAmount]);
        } else {
            DriverDailyAllowance::create(['driver_id' => $driverId, 'allowance_date' => $date->toDateString(), 'amount' => $allowanceAmount]);
        }

        $perDelivery = round($allowanceAmount / $deliveries->count(), 4);
        foreach ($deliveries as $d) {
            $d->update(['allocated_phone_allowance' => $perDelivery]);
        }
    }

    // ---------------------------------------------------------------
    // Driver Settlements
    // ---------------------------------------------------------------

    /**
     * The full daily/period driver report: every completed own-company
     * delivery for this driver in the period, each shown separately (never
     * merged), plus summary totals and the driver's actual entitlement —
     * which is deliberately NOT the same figure as company delivery
     * profit. A driver earns their fixed AED 10/AED 5 regardless of how
     * profitable or unprofitable the delivery itself was; conflating the
     * two would misrepresent what's actually owed.
     */
    public function buildDriverReport(int $driverId, \Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $deliveries = DeliveryNote::where('driver_id', $driverId)->where('delivery_type', 'own_company')->where('status', 'delivered')
            ->whereDate('delivered_at', '>=', $start)->whereDate('delivered_at', '<=', $end)
            ->with('customer', 'salesOrder')->orderBy('delivered_at')->get();
        $allowances = DriverDailyAllowance::where('driver_id', $driverId)
            ->whereDate('allowance_date', '>=', $start)->whereDate('allowance_date', '<=', $end)->get();

        $lines = $deliveries->map(function ($d) {
            $petrolAllocated = round((float) $d->allocated_petrol_cost, 2);
            return [
                'delivery' => $d,
                'order_number' => $d->salesOrder?->order_number,
                'customer' => $d->customer?->name,
                'area' => $d->emirate ?? $d->customer?->area,
                'completed_at' => $d->delivered_at,
                'delivery_charge' => (float) $d->customer_delivery_charge,
                'amount_collected' => (float) $d->amount_collected,
                'driver_fee' => (float) $d->driver_fee,
                'allocated_allowance' => (float) $d->allocated_phone_allowance,
                'allocated_petrol' => $petrolAllocated,
                'profit_loss' => $this->fullyAllocatedProfitLoss($d),
            ];
        });

        $revenue = round((float) $deliveries->sum('customer_delivery_charge'), 2);
        $collectedTotal = round((float) $deliveries->sum('amount_collected'), 2);
        $uncollected = round($revenue - $collectedTotal, 2);
        $feeTotal = round((float) $deliveries->sum('driver_fee'), 2);
        $allowanceTotal = round((float) $allowances->sum('amount'), 2);
        $petrolTotal = round((float) $deliveries->sum('allocated_petrol_cost'), 2);
        $dailyProfitLoss = round($revenue - $feeTotal - $allowanceTotal - $petrolTotal, 2);

        // Approved, unpaid driver-paid reimbursements for this driver in this window — genuinely owed to them, separate from the fixed fee/allowance.
        $reimbursementsDue = round((float) \App\Models\Expense::where('driver_id', $driverId)->where('paid_by', 'driver')
            ->where('reimbursement_status', 'pending')->whereDate('delivery_day', '>=', $start)->whereDate('delivery_day', '<=', $end)
            ->sum('total_amount'), 2);

        $entitlement = round($feeTotal + $allowanceTotal + $reimbursementsDue, 2);

        $settlementIds = $deliveries->pluck('driver_settlement_id')->filter()->unique();
        $alreadyPaid = round((float) \App\Models\DriverSettlementPayment::whereIn('driver_settlement_id', $settlementIds)->sum('amount'), 2);
        $remainingPayable = max(0, round($entitlement - $alreadyPaid, 2));

        return [
            'lines' => $lines,
            'revenue' => $revenue, 'collected_total' => $collectedTotal, 'uncollected' => $uncollected,
            'fee_total' => $feeTotal, 'allowance_total' => $allowanceTotal, 'petrol_total' => $petrolTotal,
            'daily_profit_loss' => $dailyProfitLoss, 'reimbursements_due' => $reimbursementsDue,
            'entitlement' => $entitlement, 'already_paid' => $alreadyPaid, 'remaining_payable' => $remainingPayable,
        ];
    }

    public function buildDriverSettlementPreview(int $driverId, \Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $deliveries = DeliveryNote::where('driver_id', $driverId)->where('delivery_type', 'own_company')->where('status', 'delivered')
            ->whereNull('driver_settlement_id')->whereDate('delivery_date', '>=', $start)->whereDate('delivery_date', '<=', $end)->get();
        $allowances = DriverDailyAllowance::where('driver_id', $driverId)->whereNull('driver_settlement_id')
            ->whereDate('allowance_date', '>=', $start)->whereDate('allowance_date', '<=', $end)->get();

        $feeTotal = round((float) $deliveries->sum('driver_fee'), 2);
        $allowanceTotal = round((float) $allowances->sum('amount'), 2);

        return ['deliveries' => $deliveries, 'allowances' => $allowances, 'delivery_fee_total' => $feeTotal, 'allowance_total' => $allowanceTotal, 'total_payable' => round($feeTotal + $allowanceTotal, 2)];
    }

    public function createDriverSettlement(int $driverId, \Carbon\Carbon $start, \Carbon\Carbon $end, int $userId): DriverSettlement
    {
        return DB::transaction(function () use ($driverId, $start, $end, $userId) {
            $preview = $this->buildDriverSettlementPreview($driverId, $start, $end);

            $settlement = DriverSettlement::create([
                'settlement_number' => $this->numbers->next('driver_settlement'), 'driver_id' => $driverId,
                'start_date' => $start, 'end_date' => $end, 'delivery_fee_total' => $preview['delivery_fee_total'],
                'allowance_total' => $preview['allowance_total'], 'total_payable' => $preview['total_payable'],
                'remaining_amount' => $preview['total_payable'], 'status' => 'draft', 'created_by' => $userId,
            ]);

            // Locking these to the settlement is what prevents one delivery or one daily allowance from ever appearing in two settlements.
            DeliveryNote::whereIn('id', $preview['deliveries']->pluck('id'))->update(['driver_settlement_id' => $settlement->id]);
            DriverDailyAllowance::whereIn('id', $preview['allowances']->pluck('id'))->update(['driver_settlement_id' => $settlement->id]);

            return $settlement->fresh();
        });
    }

    /**
     * Records one payment against a driver settlement. Same idempotency
     * and cash-basis fixes as payCourierBill(). Additionally splits each
     * payment deterministically between the fee and allowance buckets —
     * fees are settled first, then the allowance — and posts the two as
     * SEPARATE Expense categories ("Driver Delivery Fees" and "Driver
     * Phone/Internet Allowance"), each reflecting only the cumulative
     * amount actually paid toward that category so far.
     */
    public function paySettlement(DriverSettlement $settlement, float $amount, array $paymentDetails, string $idempotencyKey, string $proofPath, ?string $proofOriginalName, int $userId): DriverSettlementPayment
    {
        return DB::transaction(function () use ($settlement, $amount, $paymentDetails, $idempotencyKey, $proofPath, $proofOriginalName, $userId) {
            $existing = DriverSettlementPayment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $settlement = DriverSettlement::lockForUpdate()->findOrFail($settlement->id);

            $feePaidSoFar = round((float) DriverSettlementPayment::where('driver_settlement_id', $settlement->id)->sum('fee_portion'), 2);
            $allowancePaidSoFar = round((float) DriverSettlementPayment::where('driver_settlement_id', $settlement->id)->sum('allowance_portion'), 2);
            $feeRemaining = max(0, round((float) $settlement->delivery_fee_total - $feePaidSoFar, 2));

            // Fees are settled first, then the allowance — a deterministic,
            // always-reproducible split rather than an arbitrary or manual one.
            $feePortion = round(min($amount, $feeRemaining), 2);
            $allowancePortion = round($amount - $feePortion, 2);

            $payment = DriverSettlementPayment::create([
                'driver_settlement_id' => $settlement->id, 'amount' => $amount,
                'fee_portion' => $feePortion, 'allowance_portion' => $allowancePortion,
                'payment_date' => $paymentDetails['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentDetails['payment_method'] ?? 'bank',
                'payment_reference' => $paymentDetails['payment_reference'] ?? null,
                'proof_path' => $proofPath, 'proof_original_name' => $proofOriginalName,
                'idempotency_key' => $idempotencyKey, 'created_by' => $userId,
            ]);

            $totalPaid = round((float) DriverSettlementPayment::where('driver_settlement_id', $settlement->id)->sum('amount'), 2);
            $remaining = round((float) $settlement->total_payable - $totalPaid, 2);
            $settlement->update([
                'amount_paid' => $totalPaid, 'remaining_amount' => max(0, $remaining),
                'status' => $remaining <= 0 ? 'paid' : 'partially_paid',
                'payment_date' => $payment->payment_date, 'payment_method' => $payment->payment_method, 'payment_reference' => $payment->payment_reference,
            ]);

            $cumulativeFee = round($feePaidSoFar + $feePortion, 2);
            $cumulativeAllowance = round($allowancePaidSoFar + $allowancePortion, 2);

            if ($cumulativeFee > 0) {
                $this->syncExpense('driver_settlement_fee', $settlement->id, [
                    'expense_date' => $payment->payment_date, 'category' => 'Driver Delivery Fees',
                    'payee' => $settlement->driver->name, 'payment_method' => $payment->payment_method,
                    'amount_ex_tax' => $cumulativeFee, 'tax_amount' => 0, 'total_amount' => $cumulativeFee,
                    'description' => "Driver settlement {$settlement->settlement_number} — fees paid to date — {$settlement->driver->name}",
                ]);
            }
            if ($cumulativeAllowance > 0) {
                $this->syncExpense('driver_settlement_allowance', $settlement->id, [
                    'expense_date' => $payment->payment_date, 'category' => 'Driver Phone/Internet Allowance',
                    'payee' => $settlement->driver->name, 'payment_method' => $payment->payment_method,
                    'amount_ex_tax' => $cumulativeAllowance, 'tax_amount' => 0, 'total_amount' => $cumulativeAllowance,
                    'description' => "Driver settlement {$settlement->settlement_number} — allowance paid to date — {$settlement->driver->name}",
                ]);
            }

            return $payment;
        });
    }

    // ---------------------------------------------------------------
    // Vehicle expenses — petrol and maintenance, one real Expense each, analytical allocation only.
    // ---------------------------------------------------------------

    public function saveVehicleExpense(array $data, int $userId): VehicleExpense
    {
        return DB::transaction(function () use ($data, $userId) {
            $totalAmount = round((float) $data['amount_ex_tax'] + (float) ($data['tax_amount'] ?? 0), 2);
            $data['total_amount'] = $totalAmount;
            $data['created_by'] = $userId;
            $expenseRecord = VehicleExpense::create($data);

            $category = match ($expenseRecord->expense_type) {
                'petrol' => 'Delivery Petrol Expense',
                'maintenance', 'repair', 'tyres', 'registration', 'insurance' => 'Delivery Vehicle Maintenance',
                'parking', 'toll' => 'Delivery Parking/Toll',
                default => 'Other Delivery Expense',
            };

            $this->syncExpense('vehicle_expense', $expenseRecord->id, [
                'expense_date' => $expenseRecord->expense_date, 'category' => $category,
                'payee' => $expenseRecord->supplier?->name, 'payment_method' => $expenseRecord->payment_method ?: 'bank',
                'amount_ex_tax' => (float) $expenseRecord->amount_ex_tax, 'tax_amount' => (float) $expenseRecord->tax_amount, 'total_amount' => $totalAmount,
                'reference' => $expenseRecord->invoice_reference, 'description' => $expenseRecord->description ?: ucfirst($expenseRecord->expense_type),
            ]);

            return $expenseRecord->fresh();
        });
    }

    /** Analytical-only — spreads a real, already-posted vehicle expense across selected deliveries for reporting. Never creates a second accounting Expense. */
    public function allocateVehicleExpense(VehicleExpense $vehicleExpense, array $deliveryIds): void
    {
        DB::transaction(function () use ($vehicleExpense, $deliveryIds) {
            $previouslyAllocatedTo = VehicleExpenseAllocation::where('vehicle_expense_id', $vehicleExpense->id)->pluck('delivery_note_id')->all();
            VehicleExpenseAllocation::where('vehicle_expense_id', $vehicleExpense->id)->delete();

            if (!empty($deliveryIds)) {
                $perDelivery = round((float) $vehicleExpense->total_amount / count($deliveryIds), 4);
                foreach ($deliveryIds as $deliveryId) {
                    VehicleExpenseAllocation::create(['vehicle_expense_id' => $vehicleExpense->id, 'delivery_note_id' => $deliveryId, 'allocated_amount' => $perDelivery]);
                }
            }

            // Recalculate every affected delivery (both newly and previously
            // allocated) as the SUM of all its allocations — fixes the bug
            // where a second allocation silently overwrote the first instead
            // of adding to it.
            foreach (array_unique(array_merge($previouslyAllocatedTo, $deliveryIds)) as $deliveryId) {
                $this->recalculateDeliveryAllocatedCosts((int) $deliveryId);
            }
        });
    }

    /** Same allocation pattern for the new Expenses-based petrol/maintenance flow (spec: petrol is entered once through Expenses, not a separate Vehicle Expenses form). Reallocating removes old allocations for this expense first, so the exact total is always preserved. */
    public function allocateExpenseToDeliveries(Expense $expense, array $deliveryIds): void
    {
        DB::transaction(function () use ($expense, $deliveryIds) {
            $previouslyAllocatedTo = ExpenseDeliveryAllocation::where('expense_id', $expense->id)->pluck('delivery_note_id')->all();
            ExpenseDeliveryAllocation::where('expense_id', $expense->id)->delete();

            if (!empty($deliveryIds)) {
                $perDelivery = round((float) $expense->total_amount / count($deliveryIds), 4);
                foreach ($deliveryIds as $deliveryId) {
                    ExpenseDeliveryAllocation::create(['expense_id' => $expense->id, 'delivery_note_id' => $deliveryId, 'allocated_amount' => $perDelivery]);
                }
            }

            foreach (array_unique(array_merge($previouslyAllocatedTo, $deliveryIds)) as $deliveryId) {
                $this->recalculateDeliveryAllocatedCosts((int) $deliveryId);
            }
        });
    }

    /** The actual sum-not-overwrite fix: a delivery's allocated petrol/maintenance cost is always recomputed from every real allocation record that references it (across both the legacy VehicleExpense-allocation path and the new Expense-allocation path), never left as a single value one allocation call can clobber. */
    private function recalculateDeliveryAllocatedCosts(int $deliveryId): void
    {
        $delivery = DeliveryNote::find($deliveryId);
        if (!$delivery) return;

        $petrolTotal = 0.0;
        $maintenanceTotal = 0.0;

        foreach (VehicleExpenseAllocation::where('delivery_note_id', $deliveryId)->with('vehicleExpense')->get() as $alloc) {
            $isMaintenance = in_array($alloc->vehicleExpense?->expense_type, ['maintenance', 'repair', 'tyres', 'registration', 'insurance'], true);
            $isMaintenance ? $maintenanceTotal += (float) $alloc->allocated_amount : $petrolTotal += (float) $alloc->allocated_amount;
        }
        foreach (ExpenseDeliveryAllocation::where('delivery_note_id', $deliveryId)->with('expense')->get() as $alloc) {
            $category = strtolower((string) $alloc->expense?->category);
            str_contains($category, 'maintenance') || str_contains($category, 'repair') ? $maintenanceTotal += (float) $alloc->allocated_amount : $petrolTotal += (float) $alloc->allocated_amount;
        }

        $delivery->update(['allocated_petrol_cost' => round($petrolTotal, 2), 'allocated_maintenance_cost' => round($maintenanceTotal, 2)]);
    }

    // ---------------------------------------------------------------
    // Shared idempotency core — identical pattern to PayrollService.
    // ---------------------------------------------------------------

    private function syncExpense(string $sourceType, int $sourceId, array $fields): Expense
    {
        $existing = Expense::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            $existing->update($fields);
            return $existing;
        }

        $fields['expense_number'] = $this->numbers->next('expense');
        $fields['source_type'] = $sourceType;
        $fields['source_id'] = $sourceId;
        return Expense::create($fields);
    }
}
