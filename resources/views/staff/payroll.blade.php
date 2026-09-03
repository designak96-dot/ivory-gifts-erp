@extends('layouts.app')
@section('title','Payroll')
@section('subtitle','Simple monthly payroll — Current Salary + Overtime Extra, no automatic deductions')
@section('content')

<div class="card">
<form style="display:flex;gap:10px;align-items:end">
<label>Month<input type="month" name="month" value="{{ $month->format('Y-m') }}"></label>
<button class="btn">Go</button>
</form>
</div>

<div class="card" style="margin-top:15px"><div class="table-wrap"><table><thead><tr><th>Staff</th><th>Current Salary</th><th>Overtime Extra</th><th>Total to Pay</th><th>Amount Paid</th><th>Remaining</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($staffList as $s)
@php($p = $payrollByStaff->get($s->id))
@php($approvedOt = $approvedOvertimeByStaff->get($s->id, collect())->whereNull('payroll_payment_id'))
<tr>
<td><b>{{ $s->name }}</b><div class="muted">{{ $s->staff_number }}</div></td>
<td class="amount">AED {{ number_format($s->current_salary,2) }}</td>
<td class="amount">AED {{ number_format($p->overtime_extra ?? 0,2) }}</td>
<td class="amount"><b>AED {{ number_format($p->total_to_pay ?? $s->current_salary,2) }}</b></td>
<td class="amount kpi-good">AED {{ number_format($p->amount_paid ?? 0,2) }}</td>
<td class="amount kpi-bad">AED {{ number_format($p->remaining_amount ?? $s->current_salary,2) }}</td>
<td><span class="badge {{ $p?->status==='paid'?'green':($p?->status==='partially_paid'?'amber':'red') }}">{{ $p ? ucfirst(str_replace('_',' ',$p->status)) : 'Unpaid' }}</span></td>
<td>
@if(auth()->user()->hasPermission('payroll.pay'))
<details><summary class="btn small">Pay / Update</summary>
<form method="post" action="{{ route('payroll.store',$s) }}" enctype="multipart/form-data" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:220px">
@csrf
<input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
@if($approvedOt->count())
<label class="muted">Include Overtime</label>
@foreach($approvedOt as $ot)<label style="font-weight:normal"><input type="checkbox" name="overtime_ids[]" value="{{ $ot->id }}"> {{ $ot->date->format('d M') }} — AED {{ number_format($ot->amount,2) }}</label>@endforeach
@endif
<label>Amount Paid<input type="number" step="0.01" name="amount_paid" value="{{ $p->amount_paid ?? 0 }}" required></label>
<label>Payment Date<input type="date" name="payment_date" value="{{ today()->toDateString() }}"></label>
<label>Method<select name="payment_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="card">Card</option></select></label>
<label>Reference<input name="payment_reference"></label>
<label>Proof<input type="file" name="proof"></label>
<button class="btn small primary">Save</button>
</form>
</details>
@if($p && $p->status !== 'cancelled' && auth()->user()->hasPermission('payroll.cancel'))
<form method="post" action="{{ route('payroll.cancel',$p) }}" style="margin-top:4px" onsubmit="return confirm('Cancel this payroll payment and remove its linked expense?')">@csrf<button class="btn small">Cancel</button></form>
@endif
@endif
</td>
</tr>
@endforeach
</tbody></table></div></div>
@endsection
