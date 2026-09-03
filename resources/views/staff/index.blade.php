@extends('layouts.app')
@section('title','Staff')
@section('subtitle','Staff directory and salary status')
@section('content')

@if(auth()->user()->hasPermission('staff.create'))
<div class="toolbar"><a class="btn primary" href="{{ route('staff.create') }}">+ Add Staff</a></div>
@endif

<div class="card" style="margin-top:12px">
<form style="display:flex;gap:10px;flex-wrap:wrap">
<input name="q" value="{{ request('q') }}" placeholder="Search name, ID, phone, job title">
<input type="month" name="month" value="{{ $month->format('Y-m') }}">
<button class="btn">Filter</button>
</form>

<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Staff ID</th><th>Name</th><th>Phone</th><th>Job Title</th><th>Joining Date</th><th>Current Salary</th><th>Status</th><th>Salary Status ({{ $month->format('M Y') }})</th><th></th></tr></thead><tbody>
@forelse($staffList as $s)
@php($payroll = $payrollByStaff->get($s->id))
<tr>
<td><b>{{ $s->staff_number }}</b></td>
<td>{{ $s->name }}</td>
<td>{{ $s->phone?:'—' }}</td>
<td>{{ $s->job_title?:'—' }}</td>
<td>{{ $s->joining_date?->format('d M Y')??'—' }}</td>
<td class="amount">AED {{ number_format($s->current_salary,2) }}</td>
<td><span class="badge {{ $s->employment_status==='active'?'green':($s->employment_status==='on_leave'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$s->employment_status)) }}</span></td>
<td><span class="badge {{ $payroll?->status==='paid'?'green':($payroll?->status==='partially_paid'?'amber':'red') }}">{{ $payroll ? ucfirst(str_replace('_',' ',$payroll->status)) : 'Unpaid' }}</span></td>
<td><a class="btn small" href="{{ route('staff.show',$s) }}">Quick View</a></td>
</tr>
@empty
<tr><td colspan="9" class="empty">No staff added yet.</td></tr>
@endforelse
</tbody></table></div>{{ $staffList->links() }}
</div>
@endsection
