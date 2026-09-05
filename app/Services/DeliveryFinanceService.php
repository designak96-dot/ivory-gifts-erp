<?php

namespace App\Services;

use App\Models\{CourierBill, CourierBillLine, DeliveryFinanceSetting, DeliveryNote, DriverDailyAllowance, DriverSettlement, Expense, Supplier, VehicleExpense, VehicleExpenseAllocation};
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

    /** Paying the combined bill creates exactly one linked Expense — never one per delivery line. */
    public function payCourierBill(CourierBill $bill, float $amountPaid, ?array $paymentDetails, int $userId): void
    {
        DB::transaction(function () use ($bill, $amountPaid, $paymentDetails) {
            $newPaid = round((float) $bill->amount_paid + $amountPaid, 2);
            $remaining = round((float) $bill->total_amount - $newPaid, 2);
            $bill->update([
                'amount_paid' => $newPaid, 'status' => $remaining <= 0 ? 'paid' : 'partially_paid',
                'payment_date' => $paymentDetails['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentDetails['payment_method'] ?? $bill->payment_method,
                'payment_reference' => $paymentDetails['payment_reference'] ?? $bill->payment_reference,
            ]);

            $this->syncExpense('courier_bill', $bill->id, [
                'expense_date' => $bill->payment_date ?? now()->toDateString(),
                'category' => $bill->deliveries()->first()?->delivery_type === 'international_courier' ? 'International Courier Expense' : 'Domestic Courier Expense',
                'payee' => $bill->supplier->name, 'payment_method' => $bill->payment_method ?: 'bank',
                'amount_ex_tax' => (float) $bill->amount_ex_tax, 'tax_amount' => (float) $bill->tax_amount, 'total_amount' => (float) $bill->total_amount,
                'reference' => $bill->supplier_invoice_number,
                'description' => "Courier bill {$bill->bill_number} — {$bill->supplier->name} ({$bill->period_start->format('d M')}–{$bill->period_end->format('d M Y')})",
            ]);
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
        $deliveries = DeliveryNote::where('driver_id', $driverId)->where('delivery_type', 'own_company')
            ->whereDate('delivery_date', $date)->where('status', 'delivered')->get();

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

    public function paySettlement(DriverSettlement $settlement, float $amountPaid, ?array $paymentDetails, int $userId): void
    {
        DB::transaction(function () use ($settlement, $amountPaid, $paymentDetails) {
            $newPaid = round((float) $settlement->amount_paid + $amountPaid, 2);
            $remaining = round((float) $settlement->total_payable - $newPaid, 2);
            $settlement->update([
                'amount_paid' => $newPaid, 'remaining_amount' => max(0, $remaining),
                'status' => $remaining <= 0 ? 'paid' : 'partially_paid',
                'payment_date' => $paymentDetails['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentDetails['payment_method'] ?? $settlement->payment_method,
                'payment_reference' => $paymentDetails['payment_reference'] ?? $settlement->payment_reference,
            ]);

            // The driver is an employee/user — never created as a Supplier.
            $this->syncExpense('driver_settlement', $settlement->id, [
                'expense_date' => $settlement->payment_date ?? now()->toDateString(), 'category' => 'Driver Delivery Fees',
                'payee' => $settlement->driver->name, 'payment_method' => $settlement->payment_method ?: 'bank',
                'amount_ex_tax' => (float) $settlement->total_payable, 'tax_amount' => 0, 'total_amount' => (float) $settlement->total_payable,
                'description' => "Driver settlement {$settlement->settlement_number} — {$settlement->driver->name} ({$settlement->start_date->format('d M')}–{$settlement->end_date->format('d M Y')})",
            ]);
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
            VehicleExpenseAllocation::where('vehicle_expense_id', $vehicleExpense->id)->delete();
            if (empty($deliveryIds)) return;

            $perDelivery = round((float) $vehicleExpense->total_amount / count($deliveryIds), 4);
            $isMaintenance = in_array($vehicleExpense->expense_type, ['maintenance', 'repair', 'tyres', 'registration', 'insurance'], true);

            foreach ($deliveryIds as $deliveryId) {
                VehicleExpenseAllocation::create(['vehicle_expense_id' => $vehicleExpense->id, 'delivery_note_id' => $deliveryId, 'allocated_amount' => $perDelivery]);
                $delivery = DeliveryNote::find($deliveryId);
                if ($delivery) {
                    $field = $isMaintenance ? 'allocated_maintenance_cost' : 'allocated_petrol_cost';
                    $delivery->update([$field => $perDelivery]);
                }
            }
        });
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
