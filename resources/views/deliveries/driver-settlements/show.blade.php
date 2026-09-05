@extends('layouts.app')
@section('title',$settlement->settlement_number)
@section('content')
<div class="grid cols-4">
<div class="stat"><small>Driver</small><strong>{{ $settlement->driver->name }}</strong></div>
<div class="stat"><small>Total Payable</small><strong>AED {{ number_format($settlement->total_payable,2) }}</strong></div>
<div class="stat"><small>Paid</small><strong class="kpi-good">AED {{ number_format($settlement->amount_paid,2) }}</strong></div>
<div class="stat"><small>Remaining</small><strong class="kpi-bad">AED {{ number_format($settlement->remaining_amount,2) }}</strong></div>
</div>

<div class="card" style="margin-top:15px"><h2>Deliveries ({{ $settlement->deliveries->count() }})</h2>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Delivery</th><th>Date</th><th>Fee</th></tr></thead><tbody>
@foreach($settlement->deliveries as $d)<tr><td>{{ $d->delivery_note_number }}</td><td>{{ $d->delivery_date?->format('d M Y') }}</td><td class="amount">AED {{ number_format($d->driver_fee,2) }}</td></tr>@endforeach
</tbody></table></div>
<h3 style="margin-top:15px">Daily Allowances ({{ $settlement->dailyAllowances->count() }})</h3>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Date</th><th>Amount</th></tr></thead><tbody>
@foreach($settlement->dailyAllowances as $a)<tr><td>{{ $a->allowance_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($a->amount,2) }}</td></tr>@endforeach
</tbody></table></div>
</div>

@if(auth()->user()->hasPermission('driver-settlements.pay') && $settlement->status !== 'paid')
<div class="card" style="margin-top:15px"><h2>Record Payment</h2>
<form method="post" action="{{ route('driver-settlements.pay',$settlement) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
@csrf
<label>Amount<input type="number" step="0.01" name="amount_paid" value="{{ $settlement->remaining_amount }}" required></label>
<label>Date<input type="date" name="payment_date" value="{{ today()->toDateString() }}" required></label>
<label>Method<select name="payment_method"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></label>
<button class="btn primary">Record Payment</button>
</form>
</div>
@endif
@endsection
