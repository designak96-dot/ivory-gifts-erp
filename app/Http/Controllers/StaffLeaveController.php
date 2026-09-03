<?php
namespace App\Http\Controllers;
use App\Models\{Staff, StaffLeave};
use Illuminate\Http\Request;
class StaffLeaveController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.leave.manage'), 403);
        $data = $request->validate(['leave_type' => 'required|in:annual,sick,unpaid,emergency,other', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'reason' => 'nullable|string|max:255']);
        $days = \Carbon\Carbon::parse($data['start_date'])->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1;
        $staff->leaves()->create($data + ['days' => $days, 'approval_status' => 'pending']);
        return back()->with('success', 'Leave request recorded — pending approval.');
    }

    public function setStatus(Request $request, StaffLeave $leave)
    {
        abort_unless(auth()->user()->hasPermission('staff.leave.manage'), 403);
        $data = $request->validate(['approval_status' => 'required|in:approved,rejected,completed,cancelled']);
        $leave->update(['approval_status' => $data['approval_status'], 'approved_by' => auth()->id()]);
        return back()->with('success', 'Leave status updated.');
    }
}
