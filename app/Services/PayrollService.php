<?php

namespace App\Services;

use App\Models\{Expense, PayrollPayment, Staff, StaffGratuity, StaffOvertime, StaffTicket};
use Illuminate\Support\Facades\DB;

/**
 * Payroll stays deliberately simple: Current Salary + Overtime Extra =
 * Total to Pay, with no automatic deductions for absence, leave,
 * advances, or benefits. Every method here is idempotent — calling
 * "save" on the same payment twice, or the same request being
 * repeated, updates the one linked Expense rather than creating a
 * second one. This is enforced at two levels: application logic
 * (findOrCreate against source_type+source_id) AND a real database
 * unique constraint on (source_type, source_id), so even a genuine
 * race condition (e.g. a double-click landing as two near-simultaneous
 * requests) cannot slip through — the second insert fails the unique
 * constraint rather than silently succeeding twice.
 */
class PayrollService
{
    public function __construct(private NumberingService $numbers) {}

    /**
     * Records or updates a staff member's salary change, keeping the
     * old value visible in history — this does NOT touch any existing
     * PayrollPayment snapshot, which is exactly the point: old payroll
     * records must keep showing the salary that was actually paid.
     */
    public function changeSalary(Staff $staff, float $newSalary, ?string $reason, int $userId): void
    {
        DB::transaction(function () use ($staff, $newSalary, $reason, $userId) {
            $previous = (float) $staff->current_salary;
            $staff->salaryChanges()->create([
                'previous_salary' => $previous, 'new_salary' => $newSalary,
                'effective_date' => now()->toDateString(), 'reason' => $reason, 'updated_by' => $userId,
            ]);
            $staff->update(['current_salary' => $newSalary]);
        });
    }

    /**
     * Creates or updates the payroll record for one staff member for one
     * month. The salary is snapshotted at creation time and never
     * recomputed from the staff's current_salary afterward — even if
     * the staff's salary changes later, this record keeps showing what
     * was actually true for this month.
     *
     * @param array $overtimeIds Approved StaffOvertime IDs to attach as this month's Overtime Extra — each can only ever belong to one payroll record.
     */
    public function savePayroll(Staff $staff, \Carbon\Carbon $month, array $overtimeIds, float $amountPaid, ?array $paymentDetails, int $userId): PayrollPayment
    {
        return DB::transaction(function () use ($staff, $month, $overtimeIds, $amountPaid, $paymentDetails, $userId) {
            $monthStart = $month->copy()->startOfMonth();
            $existing = PayrollPayment::where('staff_id', $staff->id)->whereDate('payroll_month', $monthStart)->first();

            // Only approved overtime may be attached, and never one already claimed by a different payroll record.
            $overtimeEntries = StaffOvertime::where('staff_id', $staff->id)->where('status', 'approved')
                ->whereIn('id', $overtimeIds)
                ->where(fn ($q) => $q->whereNull('payroll_payment_id')->orWhere('payroll_payment_id', $existing?->id))
                ->get();
            $overtimeExtra = round((float) $overtimeEntries->sum('amount'), 2);

            $currentSalary = $existing ? (float) $existing->current_salary : (float) $staff->current_salary; // snapshot only set once, on first save
            $totalToPay = round($currentSalary + $overtimeExtra, 2);
            $remaining = round($totalToPay - $amountPaid, 2);
            $status = $remaining <= 0 && $amountPaid > 0 ? 'paid' : ($amountPaid > 0 ? 'partially_paid' : 'unpaid');

            $payload = [
                'staff_id' => $staff->id, 'payroll_month' => $monthStart,
                'current_salary' => $currentSalary, 'overtime_extra' => $overtimeExtra, 'total_to_pay' => $totalToPay,
                'amount_paid' => $amountPaid, 'remaining_amount' => max(0, $remaining), 'status' => $status,
                'payment_date' => $paymentDetails['payment_date'] ?? null, 'payment_method' => $paymentDetails['payment_method'] ?? null,
                'payment_reference' => $paymentDetails['payment_reference'] ?? null,
            ] + ($paymentDetails['proof_fields'] ?? []);

            if ($existing) {
                $existing->update($payload);
                $payroll = $existing;
            } else {
                $payload['payroll_number'] = $this->numbers->next('payroll_payment');
                $payload['created_by'] = $userId;
                $payroll = PayrollPayment::create($payload);
            }

            StaffOvertime::whereIn('id', $overtimeEntries->pluck('id'))->update(['payroll_payment_id' => $payroll->id, 'status' => 'paid']);
            // Any overtime that WAS attached but got deselected this save must be released, not left dangling.
            StaffOvertime::where('payroll_payment_id', $payroll->id)->whereNotIn('id', $overtimeEntries->pluck('id'))->update(['payroll_payment_id' => null, 'status' => 'approved']);

            if ($amountPaid > 0) {
                $this->syncExpense('payroll_payment', $payroll->id, [
                    'expense_date' => $payroll->payment_date ?? now()->toDateString(), 'category' => 'Staff Salary',
                    'payee' => $staff->name, 'payment_method' => $payroll->payment_method ?: 'bank',
                    'amount_ex_tax' => $amountPaid, 'tax_amount' => 0, 'total_amount' => $amountPaid,
                    'reference' => $payroll->payment_reference, 'description' => "Salary — {$staff->name} — {$monthStart->format('F Y')}",
                    'staff_id' => $staff->id, 'payroll_period' => $monthStart->format('Y-m'),
                ] + ($paymentDetails['proof_fields'] ?? []));
            }

            return $payroll->fresh();
        });
    }

    /** Links an existing Expense record to a historical payroll entry instead of creating a new one — the spec's "existing expense protection" requirement. */
    public function linkExistingExpense(PayrollPayment $payroll, Expense $expense): void
    {
        $expense->update(['source_type' => 'payroll_payment', 'source_id' => $payroll->id, 'staff_id' => $payroll->staff_id, 'payroll_period' => $payroll->payroll_month->format('Y-m')]);
        $payroll->update(['linked_from_existing_expense' => true]);
    }

    public function payTicket(StaffTicket $ticket, int $userId): void
    {
        DB::transaction(function () use ($ticket, $userId) {
            $ticket->update(['status' => 'purchased']);
            if ((float) $ticket->amount > 0) {
                $this->syncExpense('staff_ticket', $ticket->id, [
                    'expense_date' => $ticket->payment_date ?? now()->toDateString(), 'category' => 'Staff Air Ticket',
                    'payee' => $ticket->supplier?->name ?: ($ticket->destination ? "Travel — {$ticket->destination}" : 'Travel'),
                    'payment_method' => $ticket->payment_method ?: 'bank',
                    'amount_ex_tax' => $ticket->amount, 'tax_amount' => 0, 'total_amount' => $ticket->amount,
                    'reference' => $ticket->invoice_reference, 'description' => "Air ticket — {$ticket->staff->name} — {$ticket->destination}",
                    'staff_id' => $ticket->staff_id,
                ]);
            }
        });
    }

    public function payGratuity(StaffGratuity $gratuity, float $amountPaid, ?array $paymentDetails, int $userId): void
    {
        DB::transaction(function () use ($gratuity, $amountPaid, $paymentDetails) {
            $approved = (float) ($gratuity->approved_amount ?? $gratuity->estimated_amount ?? 0);
            $remaining = round($approved - $amountPaid, 2);
            $gratuity->update([
                'amount_paid' => $amountPaid, 'remaining_amount' => max(0, $remaining),
                'status' => $remaining <= 0 && $amountPaid > 0 ? 'paid' : ($amountPaid > 0 ? 'partially_paid' : $gratuity->status),
                'payment_date' => $paymentDetails['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentDetails['payment_method'] ?? null, 'payment_reference' => $paymentDetails['payment_reference'] ?? null,
            ]);

            // An estimate must never create an Expense — only an actual payment does.
            if ($amountPaid > 0) {
                $this->syncExpense('staff_gratuity', $gratuity->id, [
                    'expense_date' => $gratuity->payment_date ?? now()->toDateString(), 'category' => 'Staff Gratuity',
                    'payee' => $gratuity->staff->name, 'payment_method' => $gratuity->payment_method ?: 'bank',
                    'amount_ex_tax' => $amountPaid, 'tax_amount' => 0, 'total_amount' => $amountPaid,
                    'reference' => $gratuity->payment_reference, 'description' => "Gratuity — {$gratuity->staff->name}",
                    'staff_id' => $gratuity->staff_id,
                ]);
            }
        });
    }

    /**
     * The idempotency core: finds the existing Expense for this exact
     * source_type+source_id and updates it, or creates exactly one new
     * one. A unique DB constraint on (source_type, source_id) backs
     * this up — even a race condition cannot create two.
     */
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

    /** Cancelling a payment reverses the linked Expense exactly once — deletes it and clears the payroll/ticket/gratuity status, rather than leaving a stale accounting record. */
    public function cancelPayrollPayment(PayrollPayment $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            Expense::where('source_type', 'payroll_payment')->where('source_id', $payroll->id)->delete();
            $payroll->update(['status' => 'cancelled']);
            StaffOvertime::where('payroll_payment_id', $payroll->id)->update(['payroll_payment_id' => null, 'status' => 'approved']);
        });
    }
}
