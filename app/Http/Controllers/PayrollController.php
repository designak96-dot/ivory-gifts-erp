<?php

namespace App\Http\Controllers;

use App\Models\{Expense, PayrollPayment, Staff, StaffOvertime};
use App\Services\{PayrollService, ProofUploadService};
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('payroll.manage'), 403);
        $month = $request->filled('month') ? \Carbon\Carbon::parse($request->query('month').'-01') : now()->startOfMonth();

        $staffList = Staff::where('employment_status', 'active')->orderBy('name')->get();
        $payrollByStaff = PayrollPayment::whereIn('staff_id', $staffList->pluck('id'))->whereDate('payroll_month', $month->copy()->startOfMonth())->get()->keyBy('staff_id');
        $approvedOvertimeByStaff = StaffOvertime::whereIn('staff_id', $staffList->pluck('id'))->where('status', 'approved')->orderBy('date')->get()->groupBy('staff_id');

        return view('staff.payroll', compact('staffList', 'month', 'payrollByStaff', 'approvedOvertimeByStaff'));
    }

    public function store(Request $request, Staff $staff, PayrollService $payroll, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('payroll.pay'), 403);
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'overtime_ids' => 'nullable|array', 'overtime_ids.*' => 'integer',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'nullable|date', 'payment_method' => 'nullable|in:cash,bank,card', 'payment_reference' => 'nullable|string|max:100',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);

        $paymentDetails = ['payment_date' => $data['payment_date'] ?? null, 'payment_method' => $data['payment_method'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null];
        if ($request->hasFile('proof')) {
            $stored = $proofs->store($request->file('proof'), 'staff-payroll-proofs');
            $paymentDetails['proof_fields'] = ['proof_path' => $stored['proof_path'], 'proof_original_name' => $stored['proof_original_name'], 'proof_mime' => $stored['proof_mime'], 'proof_size' => $stored['proof_size']];
        }

        $month = \Carbon\Carbon::parse($data['month'].'-01');
        $payroll->savePayroll($staff, $month, $data['overtime_ids'] ?? [], (float) $data['amount_paid'], $paymentDetails, auth()->id());

        return back()->with('success', "Payroll saved for {$staff->name} — {$month->format('F Y')}.");
    }

    public function cancel(PayrollPayment $payrollPayment, PayrollService $payroll)
    {
        abort_unless(auth()->user()->hasPermission('payroll.cancel'), 403);
        $payroll->cancelPayrollPayment($payrollPayment);
        return back()->with('success', 'Payroll payment cancelled and its linked accounting entry removed.');
    }

    /** Links an existing Expense (created before this module existed) to a historical payroll record, instead of creating a duplicate. */
    public function linkExpense(Request $request, PayrollPayment $payrollPayment, PayrollService $payroll)
    {
        abort_unless(auth()->user()->hasPermission('payroll.manage'), 403);
        $data = $request->validate(['expense_id' => 'required|exists:expenses,id']);
        $expense = Expense::whereNull('source_type')->findOrFail($data['expense_id']);
        $payroll->linkExistingExpense($payrollPayment, $expense);
        return back()->with('success', 'Existing expense linked to this payroll record.');
    }
}
