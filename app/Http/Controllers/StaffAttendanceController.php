<?php
namespace App\Http\Controllers;
use App\Models\Staff;
use Illuminate\Http\Request;
class StaffAttendanceController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.attendance.manage'), 403);
        $data = $request->validate(['date' => 'required|date', 'status' => 'required|in:present,absent,half_day,on_leave,sick_leave,weekly_off,public_holiday', 'notes' => 'nullable|string|max:255']);
        $staff->attendance()->updateOrCreate(['date' => $data['date']], $data); // one record per staff per day — resubmitting corrects it, never duplicates
        return back()->with('success', 'Attendance recorded.');
    }
}
