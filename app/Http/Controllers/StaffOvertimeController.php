<?php
namespace App\Http\Controllers;
use App\Models\{Staff, StaffOvertime};
use Illuminate\Http\Request;
class StaffOvertimeController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.overtime.approve') || auth()->user()->hasPermission('payroll.manage'), 403);
        $data = $request->validate(['date' => 'required|date', 'hours' => 'nullable|numeric|min:0', 'rate' => 'nullable|numeric|min:0', 'amount' => 'required|numeric|min:0.01', 'reason' => 'nullable|string|max:255']);
        $staff->overtime()->create($data + ['status' => 'pending']);
        return back()->with('success', 'Overtime recorded — pending approval.');
    }

    public function setStatus(Request $request, StaffOvertime $overtime)
    {
        abort_unless(auth()->user()->hasPermission('staff.overtime.approve'), 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        abort_if($overtime->status === 'paid', 422, 'This overtime has already been paid and cannot be changed.');
        $overtime->update(['status' => $data['status'], 'approved_by' => auth()->id()]);
        return back()->with('success', "Overtime {$data['status']}.");
    }
}
