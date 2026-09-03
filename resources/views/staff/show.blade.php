@extends('layouts.app')
@section('title',$staff->name)
@section('subtitle',$staff->staff_number.' · '.($staff->job_title?:'Staff member'))
@section('content')

<p><a href="{{ route('staff.index') }}">&larr; Back to Staff</a> @if(auth()->user()->hasPermission('staff.edit'))· <a href="{{ route('staff.edit',$staff) }}">Edit Profile</a>@endif</p>

<div class="grid cols-4" style="margin-top:12px">
<div class="stat"><small>Current Salary</small><strong>{{ $canViewSalary ? 'AED '.number_format($staff->current_salary,2) : '—' }}</strong></div>
<div class="stat"><small>Employment Status</small><strong><span class="badge {{ $staff->employment_status==='active'?'green':'amber' }}">{{ ucfirst(str_replace('_',' ',$staff->employment_status)) }}</span></strong></div>
<div class="stat"><small>Joining Date</small><strong>{{ $staff->joining_date?->format('d M Y')??'—' }}</strong></div>
<div class="stat"><small>Job Title</small><strong>{{ $staff->job_title?:'—' }}</strong></div>
</div>

<div class="card" style="margin-top:18px">
<div style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:10px" id="staff-tabs">
<button class="btn small tab-btn active" data-tab="overview">Overview</button>
<button class="btn small tab-btn" data-tab="salary">Salary History</button>
<button class="btn small tab-btn" data-tab="overtime">Overtime</button>
<button class="btn small tab-btn" data-tab="attendance">Attendance & Absence</button>
<button class="btn small tab-btn" data-tab="leave">Vacation & Leave</button>
<button class="btn small tab-btn" data-tab="tickets">Tickets</button>
<button class="btn small tab-btn" data-tab="gratuity">Gratuity</button>
<button class="btn small tab-btn" data-tab="documents">Documents</button>
<button class="btn small tab-btn" data-tab="audit">Audit History</button>
</div>

<div class="tab-pane" data-pane="overview" style="margin-top:15px">
<div class="form-grid">
<div><b>Phone:</b> {{ $staff->phone?:'—' }}</div>
<div><b>Email:</b> {{ $staff->email?:'—' }}</div>
<div><b>Nationality:</b> {{ $staff->nationality?:'—' }}</div>
<div><b>Bank:</b> {{ $staff->bank_name?:'—' }} {{ $staff->bank_account_number }}</div>
<div><b>IBAN:</b> {{ $staff->bank_iban?:'—' }}</div>
<div><b>Emergency Contact:</b> {{ $staff->emergency_contact_name?:'—' }} ({{ $staff->emergency_contact_relation }}) {{ $staff->emergency_contact_phone }}</div>
<div><b>Passport:</b> {{ $staff->passport_number?:'—' }} @if($staff->passport_expiry) — expires {{ $staff->passport_expiry->format('d M Y') }}@endif</div>
<div><b>Visa:</b> {{ $staff->visa_number?:'—' }} ({{ $staff->visa_type }}) @if($staff->visa_expiry) — expires {{ $staff->visa_expiry->format('d M Y') }}@endif</div>
<div><b>Emirates ID:</b> {{ $staff->emirates_id_number?:'—' }} @if($staff->emirates_id_expiry) — expires {{ $staff->emirates_id_expiry->format('d M Y') }}@endif</div>
<div class="span-2"><b>Notes:</b> {{ $staff->notes?:'—' }}</div>
</div>
</div>

<div class="tab-pane" data-pane="salary" style="margin-top:15px;display:none">
<h3>Salary Change History</h3>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Effective Date</th><th>Previous</th><th>New</th><th>Reason</th><th>By</th></tr></thead><tbody>
@forelse($staff->salaryChanges as $c)<tr><td>{{ $c->effective_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($c->previous_salary,2) }}</td><td class="amount">AED {{ number_format($c->new_salary,2) }}</td><td>{{ $c->reason?:'—' }}</td><td>{{ $c->updater?->name?:'—' }}</td></tr>@empty<tr><td colspan="5" class="empty">No salary changes yet.</td></tr>@endforelse
</tbody></table></div>

<h3 style="margin-top:18px">Payroll History</h3>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Month</th><th>Salary</th><th>Overtime</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
@forelse($staff->payrollPayments as $p)<tr><td>{{ $p->payroll_month->format('F Y') }}</td><td class="amount">AED {{ number_format($p->current_salary,2) }}</td><td class="amount">AED {{ number_format($p->overtime_extra,2) }}</td><td class="amount"><b>AED {{ number_format($p->total_to_pay,2) }}</b></td><td class="amount kpi-good">AED {{ number_format($p->amount_paid,2) }}</td><td class="amount kpi-bad">AED {{ number_format($p->remaining_amount,2) }}</td><td><span class="badge {{ $p->status==='paid'?'green':($p->status==='partially_paid'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$p->status)) }}</span></td></tr>@empty<tr><td colspan="7" class="empty">No payroll records yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="overtime" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('payroll.manage'))
<form method="post" action="{{ route('staff.overtime.store',$staff) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Date<input type="date" name="date" value="{{ today()->toDateString() }}" required></label>
<label>Hours<input type="number" step="0.5" name="hours"></label>
<label>Rate<input type="number" step="0.01" name="rate"></label>
<label>Amount<input type="number" step="0.01" name="amount" required></label>
<label>Reason<input name="reason"></label>
<button class="btn small primary">Add Overtime</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Date</th><th>Hours</th><th>Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($staff->overtime as $o)<tr><td>{{ $o->date->format('d M Y') }}</td><td>{{ $o->hours?:'—' }}</td><td class="amount">AED {{ number_format($o->amount,2) }}</td><td>{{ $o->reason?:'—' }}</td><td><span class="badge {{ $o->status==='approved'?'green':($o->status==='paid'?'blue':($o->status==='rejected'?'red':'amber')) }}">{{ ucfirst($o->status) }}</span></td>
<td>@if($o->status==='pending'&&auth()->user()->hasPermission('staff.overtime.approve'))<form method="post" action="{{ route('staff.overtime.status',$o) }}" style="display:inline">@csrf<input type="hidden" name="status" value="approved"><button class="btn small success">Approve</button></form><form method="post" action="{{ route('staff.overtime.status',$o) }}" style="display:inline">@csrf<input type="hidden" name="status" value="rejected"><button class="btn small">Reject</button></form>@endif</td>
</tr>@empty<tr><td colspan="6" class="empty">No overtime recorded yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="attendance" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('staff.attendance.manage'))
<form method="post" action="{{ route('staff.attendance.store',$staff) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Date<input type="date" name="date" value="{{ today()->toDateString() }}" required></label>
<label>Status<select name="status"><option value="present">Present</option><option value="absent">Absent</option><option value="half_day">Half Day</option><option value="on_leave">On Leave</option><option value="sick_leave">Sick Leave</option><option value="weekly_off">Weekly Off</option><option value="public_holiday">Public Holiday</option></select></label>
<label>Notes<input name="notes"></label>
<button class="btn small primary">Record</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($staff->attendance()->limit(60)->get() as $a)<tr><td>{{ $a->date->format('d M Y') }}</td><td><span class="badge {{ $a->status==='present'?'green':($a->status==='absent'?'red':'amber') }}">{{ ucfirst(str_replace('_',' ',$a->status)) }}</span></td><td>{{ $a->notes?:'—' }}</td></tr>@empty<tr><td colspan="3" class="empty">No attendance recorded yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="leave" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('staff.leave.manage'))
<form method="post" action="{{ route('staff.leaves.store',$staff) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Type<select name="leave_type"><option value="annual">Annual</option><option value="sick">Sick</option><option value="unpaid">Unpaid</option><option value="emergency">Emergency</option><option value="other">Other</option></select></label>
<label>Start<input type="date" name="start_date" required></label>
<label>End<input type="date" name="end_date" required></label>
<label>Reason<input name="reason"></label>
<button class="btn small primary">Request Leave</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($staff->leaves as $l)<tr><td>{{ ucfirst($l->leave_type) }}</td><td>{{ $l->start_date->format('d M Y') }}</td><td>{{ $l->end_date->format('d M Y') }}</td><td>{{ $l->days }}</td><td><span class="badge {{ $l->approval_status==='approved'?'green':($l->approval_status==='rejected'?'red':'amber') }}">{{ ucfirst($l->approval_status) }}</span></td>
<td>@if($l->approval_status==='pending'&&auth()->user()->hasPermission('staff.leave.manage'))<form method="post" action="{{ route('staff.leaves.status',$l) }}" style="display:inline">@csrf<input type="hidden" name="approval_status" value="approved"><button class="btn small success">Approve</button></form>@endif</td>
</tr>@empty<tr><td colspan="6" class="empty">No leave records yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="tickets" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('staff.tickets.manage'))
<form method="post" action="{{ route('staff.tickets.store',$staff) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Destination<input name="destination"></label>
<label>Travel Date<input type="date" name="travel_date"></label>
<label>Amount<input type="number" step="0.01" name="amount" required></label>
<label>Airline/Agency<input name="agency_name"></label>
<button class="btn small primary">Add Ticket</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Destination</th><th>Travel Date</th><th>Amount</th><th>Agency</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($staff->tickets as $t)<tr><td>{{ $t->destination?:'—' }}</td><td>{{ $t->travel_date?->format('d M Y')??'—' }}</td><td class="amount">AED {{ number_format($t->amount,2) }}</td><td>{{ $t->supplier?->name?:'—' }}</td><td><span class="badge {{ $t->status==='purchased'?'green':'amber' }}">{{ ucfirst($t->status) }}</span></td>
<td>@if($t->status!=='purchased'&&$t->status!=='cancelled'&&auth()->user()->hasPermission('staff.tickets.manage'))<form method="post" action="{{ route('staff.tickets.pay',$t) }}" style="display:inline">@csrf<input type="hidden" name="payment_method" value="bank"><input type="hidden" name="payment_date" value="{{ today()->toDateString() }}"><button class="btn small success">Mark Purchased</button></form>@endif</td>
</tr>@empty<tr><td colspan="6" class="empty">No tickets recorded yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="gratuity" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('staff.gratuity.view'))
<form method="post" action="{{ route('staff.gratuity.store',$staff) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Estimated Amount<input type="number" step="0.01" name="estimated_amount" required></label>
<button class="btn small primary">Add Estimate</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Service Period</th><th>Estimated</th><th>Approved</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
@forelse($staff->gratuityRecords as $g)<tr><td>{{ $g->service_period?:'—' }}</td><td class="amount">AED {{ number_format($g->estimated_amount,2) }}</td><td class="amount">{{ $g->approved_amount!==null?'AED '.number_format($g->approved_amount,2):'—' }}</td><td class="amount kpi-good">AED {{ number_format($g->amount_paid,2) }}</td><td class="amount kpi-bad">AED {{ number_format($g->remaining_amount,2) }}</td><td><span class="badge {{ $g->status==='paid'?'green':'amber' }}">{{ ucfirst(str_replace('_',' ',$g->status)) }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No gratuity records yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="documents" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('staff.edit'))
<form method="post" action="{{ route('staff.documents.store',$staff) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end">
@csrf
<label>Category<input name="category" placeholder="e.g. Passport copy"></label>
<label>File<input type="file" name="file" required></label>
<button class="btn small primary">Upload</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Category</th><th>File</th><th>Uploaded</th></tr></thead><tbody>
@forelse($staff->documents as $d)<tr><td>{{ $d->category?:'—' }}</td><td><a href="{{ route('staff.documents.download',$d) }}" target="_blank">{{ $d->original_name }}</a></td><td>{{ $d->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="3" class="empty">No documents uploaded yet.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="tab-pane" data-pane="audit" style="margin-top:15px;display:none">
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Action</th><th>By</th></tr></thead><tbody>
@forelse(\App\Models\AuditLog::where('auditable_type',\App\Models\Staff::class)->where('auditable_id',$staff->id)->latest()->limit(50)->get() as $log)
<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ ucfirst($log->action) }}</td><td>{{ optional(\App\Models\User::find($log->user_id))->name?:'System' }}</td></tr>
@empty<tr><td colspan="3" class="empty">No audit history yet.</td></tr>@endforelse
</tbody></table></div>
</div>

</div>

@push('scripts')
<script>
(function(){
  document.querySelectorAll('#staff-tabs .tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('#staff-tabs .tab-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var target = btn.dataset.tab;
      document.querySelectorAll('.tab-pane').forEach(function(pane){
        pane.style.display = pane.dataset.pane === target ? '' : 'none';
      });
    });
  });
})();
</script>
@endpush
@endsection
