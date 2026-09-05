@extends('layouts.app')
@section('title','Settlement Preview — '.$driver->name)
@section('content')
<div class="card">
<h2>{{ $driver->name }} — {{ \Carbon\Carbon::parse($data['start_date'])->format('d M') }} to {{ \Carbon\Carbon::parse($data['end_date'])->format('d M Y') }}</h2>
<div class="grid cols-3" style="margin-top:15px">
<div class="stat"><small>Delivery Fees</small><strong>AED {{ number_format($preview['delivery_fee_total'],2) }}</strong></div>
<div class="stat"><small>Daily Allowances</small><strong>AED {{ number_format($preview['allowance_total'],2) }}</strong></div>
<div class="stat"><small>Total Payable</small><strong>AED {{ number_format($preview['total_payable'],2) }}</strong></div>
</div>
<p class="muted" style="margin-top:10px">{{ $preview['deliveries']->count() }} deliveries, {{ $preview['allowances']->count() }} daily allowance day(s) in this period.</p>

@if(auth()->user()->hasPermission('driver-settlements.manage'))
<form method="post" action="{{ route('driver-settlements.store') }}" style="margin-top:15px">
@csrf
<input type="hidden" name="driver_id" value="{{ $driver->id }}">
<input type="hidden" name="start_date" value="{{ $data['start_date'] }}">
<input type="hidden" name="end_date" value="{{ $data['end_date'] }}">
<button class="btn primary">Confirm & Create Settlement</button>
</form>
@endif
</div>
@endsection
