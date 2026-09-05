@extends('layouts.app')
@section('title',$bill->bill_number)
@section('content')
<div class="grid cols-4">
<div class="stat"><small>Courier</small><strong>{{ $bill->supplier->name }}</strong></div>
<div class="stat"><small>Total</small><strong>{{ $bill->currency }} {{ number_format($bill->total_amount,2) }}</strong></div>
<div class="stat"><small>Paid</small><strong class="kpi-good">AED {{ number_format($bill->amount_paid,2) }}</strong></div>
<div class="stat"><small>Remaining</small><strong class="kpi-bad">AED {{ number_format($bill->remainingAmount(),2) }}</strong></div>
</div>

<div class="card" style="margin-top:15px"><h2>Delivery Lines ({{ $bill->lines->count() }})</h2>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Delivery</th><th>Customer</th><th>Estimated</th><th>Actual Billed</th><th>Difference</th></tr></thead><tbody>
@foreach($bill->lines as $l)
<tr><td>{{ $l->delivery->delivery_note_number }}</td><td>{{ $l->delivery->customer->name }}</td><td class="amount">AED {{ number_format($l->estimated_cost,2) }}</td><td class="amount">AED {{ number_format($l->actual_billed_cost,2) }}</td><td class="amount {{ $l->difference()>0?'kpi-bad':'kpi-good' }}">AED {{ number_format($l->difference(),2) }}</td></tr>
@endforeach
</tbody></table></div>
</div>

@if(auth()->user()->hasPermission('courier-bills.pay') && $bill->status !== 'paid')
<div class="card" style="margin-top:15px"><h2>Record Payment</h2>
<form method="post" action="{{ route('courier-bills.pay',$bill) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
@csrf
<label>Amount<input type="number" step="0.01" name="amount_paid" value="{{ $bill->remainingAmount() }}" required></label>
<label>Date<input type="date" name="payment_date" value="{{ today()->toDateString() }}" required></label>
<label>Method<select name="payment_method"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></label>
<label>Reference<input name="payment_reference"></label>
<button class="btn primary">Record Payment</button>
</form>
</div>
@endif
@endsection
