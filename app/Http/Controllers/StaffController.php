<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Services\{NumberingService, PayrollService, ProofUploadService};
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('staff.view'), 403);
        $month = $request->filled('month') ? \Carbon\Carbon::parse($request->query('month').'-01') : now();

        $q = Staff::query();
        if ($s = $request->query('q')) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('staff_number', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('job_title', 'like', "%{$s}%"));
        }
        $staffList = $q->orderBy('name')->paginate(25)->withQueryString();
        // Payroll status per staff for the selected month, computed once per page rather than N+1 queries.
        $payrollByStaff = \App\Models\PayrollPayment::whereIn('staff_id', $staffList->pluck('id'))->whereDate('payroll_month', $month->copy()->startOfMonth())->get()->keyBy('staff_id');

        return view('staff.index', ['staffList' => $staffList, 'month' => $month, 'payrollByStaff' => $payrollByStaff]);
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('staff.create'), 403);
        return view('staff.form', ['staff' => new Staff]);
    }

    public function store(Request $request, NumberingService $numbers)
    {
        abort_unless(auth()->user()->hasPermission('staff.create'), 403);
        $data = $this->validated($request);
        $data['staff_number'] = $numbers->next('staff');
        $data['created_by'] = auth()->id();
        $staff = Staff::create($data);
        return redirect()->route('staff.show', $staff)->with('success', "Staff member {$staff->name} added.");
    }

    public function edit(Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.edit'), 403);
        return view('staff.form', compact('staff'));
    }

    public function update(Request $request, Staff $staff, PayrollService $payroll)
    {
        abort_unless(auth()->user()->hasPermission('staff.edit'), 403);
        $data = $this->validated($request);

        // Only Owner/Admin may change the salary, and every change goes through the
        // service so it's recorded in Salary Change History — never a silent overwrite.
        $newSalary = (float) $data['current_salary'];
        unset($data['current_salary']);
        $staff->update($data);
        if (abs($newSalary - (float) $staff->current_salary) > 0.001) {
            abort_unless(auth()->user()->hasPermission('payroll.manage'), 403, 'Only Owner/Admin may change the salary.');
            $payroll->changeSalary($staff, $newSalary, $request->input('salary_change_reason'), auth()->id());
        }

        return redirect()->route('staff.show', $staff)->with('success', 'Staff profile updated.');
    }

    public function show(Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.view'), 403);
        return view('staff.show', [
            'staff' => $staff->load('documents', 'creator'),
            'canViewSalary' => auth()->user()->hasPermission('staff.salary.view'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:190', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:190',
            'nationality' => 'nullable|string|max:80', 'job_title' => 'nullable|string|max:120', 'joining_date' => 'nullable|date',
            'current_salary' => 'required|numeric|min:0', 'employment_status' => 'required|in:active,on_leave,resigned,terminated',
            'bank_name' => 'nullable|string|max:120', 'bank_account_number' => 'nullable|string|max:60', 'bank_iban' => 'nullable|string|max:60',
            'emergency_contact_name' => 'nullable|string|max:120', 'emergency_contact_phone' => 'nullable|string|max:30', 'emergency_contact_relation' => 'nullable|string|max:60',
            'passport_number' => 'nullable|string|max:60', 'passport_expiry' => 'nullable|date',
            'visa_number' => 'nullable|string|max:60', 'visa_type' => 'nullable|string|max:60', 'visa_expiry' => 'nullable|date',
            'emirates_id_number' => 'nullable|string|max:60', 'emirates_id_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }

    public function uploadDocument(Request $request, Staff $staff, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('staff.edit'), 403);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192', 'category' => 'nullable|string|max:60']);
        $stored = $proofs->store($request->file('file'), 'staff-documents');
        $staff->documents()->create(['category' => $request->input('category'), 'file_path' => $stored['proof_path'], 'original_name' => $stored['proof_original_name'], 'mime' => $stored['proof_mime'], 'size' => $stored['proof_size'], 'uploaded_by' => auth()->id()]);
        return back()->with('success', 'Document uploaded.');
    }

    public function downloadDocument(\App\Models\StaffDocument $document)
    {
        abort_unless(auth()->user()->hasPermission('staff.view'), 403);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->response($document->file_path, $document->original_name);
    }
}
