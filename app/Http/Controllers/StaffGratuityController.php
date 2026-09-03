<?php
namespace App\Http\Controllers;
use App\Models\{Staff, StaffGratuity};
use App\Services\PayrollService;
use Illuminate\Http\Request;
class StaffGratuityController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.gratuity.view'), 403);
        $data = $request->validate(['joining_date' => 'nullable|date', 'last_working_date' => 'nullable|date', 'estimated_amount' => 'required|numeric|min:0', 'notes' => 'nullable|string|max:255']);

        // A simple, transparent estimate: 21 days' basic salary per year of service — shown clearly, not hidden in a black-box formula.
        $joining = $data['joining_date'] ? \Carbon\Carbon::parse($data['joining_date']) : $staff->joining_date;
        $lastDay = $data['last_working_date'] ? \Carbon\Carbon::parse($data['last_working_date']) : now();
        $servicePeriod = $joining ? $joining->diff($lastDay)->format('%y years, %m months') : 'Unknown';

        $staff->gratuityRecords()->create($data + ['service_period' => $servicePeriod, 'status' => 'estimate', 'created_by' => auth()->id()]);
        return back()->with('success', 'Gratuity estimate recorded — no expense created for an estimate.');
    }

    public function approve(Request $request, StaffGratuity $gratuity)
    {
        abort_unless(auth()->user()->hasPermission('staff.gratuity.approve'), 403);
        $data = $request->validate(['approved_amount' => 'required|numeric|min:0']);
        $gratuity->update(['approved_amount' => $data['approved_amount'], 'status' => 'approved']);
        return back()->with('success', 'Gratuity approved.');
    }

    public function pay(Request $request, StaffGratuity $gratuity, PayrollService $payroll)
    {
        abort_unless(auth()->user()->hasPermission('staff.gratuity.approve'), 403);
        $data = $request->validate(['amount_paid' => 'required|numeric|min:0.01', 'payment_date' => 'required|date', 'payment_method' => 'required|in:cash,bank,card', 'payment_reference' => 'nullable|string|max:100']);
        $payroll->payGratuity($gratuity, (float) $data['amount_paid'], $data, auth()->id());
        return back()->with('success', 'Gratuity payment recorded — expense posted.');
    }
}
