@extends('layouts.app')
@section('title','Driver Settlements')
@section('content')
@if(auth()->user()->hasPermission('driver-settlements.manage'))
<div class="card">
<h2>New Settlement Preview</h2>
<form method="post" action="{{ route('driver-settlements.preview') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
@csrf
<label>Driver<select name="driver_id" required><option value="">Select...</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
<label>Start<input type="date" name="start_date" required></label>
<label>End<input type="date" name="end_date" required></label>
<button class="btn primary">Preview</button>
</form>
</div>
@endif

<div class="card" style="margin-top:15px"><div class="table-wrap"><table><thead><tr><th>Settlement #</th><th>Driver</th><th>Period</th><th>Total Payable</th><th>Paid</th><th>Status</th></tr></thead><tbody>
@forelse($settlements as $s)
<tr>
<td><a href="{{ route('driver-settlements.show',$s) }}"><b>{{ $s->settlement_number }}</b></a></td>
<td>{{ $s->driver->name }}</td>
<td>{{ $s->start_date->format('d M') }} – {{ $s->end_date->format('d M Y') }}</td>
<td class="amount"><b>AED {{ number_format($s->total_payable,2) }}</b></td>
<td class="amount kpi-good">AED {{ number_format($s->amount_paid,2) }}</td>
<td><span class="badge {{ $s->status==='paid'?'green':($s->status==='partially_paid'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td>
</tr>
@empty<tr><td colspan="6" class="empty">No settlements yet.</td></tr>@endforelse
</tbody></table></div>{{ $settlements->links() }}</div>
@endsection
