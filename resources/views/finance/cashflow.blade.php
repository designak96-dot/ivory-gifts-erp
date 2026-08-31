@extends('layouts.app')
@section('title','Cashflow Dashboard')
@section('subtitle','Cash movement, receivables and payables for the selected period')
@section('content')
<form class="toolbar"><label>From<input type="date" name="from" value="{{ $from }}"></label><label>To<input type="date" name="to" value="{{ $to }}"></label><button class="btn primary">Apply</button>
<a class="btn small" href="{{ route('cashflow.index',['from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()]) }}">This month</a>
<a class="btn small" href="{{ route('cashflow.index',['from'=>now()->subMonth()->startOfMonth()->toDateString(),'to'=>now()->subMonth()->endOfMonth()->toDateString()]) }}">Last month</a>
</form>

<div class="grid stats">
<div class="stat"><small>Cash in</small><strong class="kpi-good">AED {{ number_format($cashflow['cash_in'],2) }}</strong></div>
<div class="stat"><small>Cash out</small><strong class="kpi-bad">AED {{ number_format($cashflow['cash_out'],2) }}</strong></div>
<div class="stat"><small>Net cashflow</small><strong class="{{ $cashflow['net_cashflow']>=0?'kpi-good':'kpi-bad' }}">AED {{ number_format($cashflow['net_cashflow'],2) }}</strong></div>
<div class="stat"><small>Bank balance</small><strong>AED {{ number_format($cashflow['bank_balance'],2) }}</strong></div>
<div class="stat"><small>Cash balance</small><strong>AED {{ number_format($cashflow['cash_balance'],2) }}</strong></div>
<div class="stat"><small>Receivables</small><strong class="kpi-bad">AED {{ number_format($cashflow['receivables'],2) }}</strong></div>
<div class="stat"><small>Payables</small><strong class="kpi-bad">AED {{ number_format($cashflow['payables'],2) }}</strong></div>
</div>
@endsection
