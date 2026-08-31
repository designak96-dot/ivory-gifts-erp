@extends('layouts.app')
@section('title','VAT Report')
@section('subtitle','UAE VAT position for the selected period')
@section('content')
<form class="toolbar"><label>From<input type="date" name="from" value="{{ $from }}"></label><label>To<input type="date" name="to" value="{{ $to }}"></label><button class="btn primary">Apply</button><a class="btn" href="{{ route('vat.export',['from'=>$from,'to'=>$to]) }}">Export CSV</a></form>

<div class="grid stats">
<div class="stat"><small>Sales VAT (output)</small><strong>AED {{ number_format($salesVat,2) }}</strong><em>{{ $invoiceCount }} invoices</em></div>
<div class="stat"><small>Expense VAT (input)</small><strong>AED {{ number_format($inputVat,2) }}</strong><em>{{ $expenseCount }} expenses</em></div>
<div class="stat"><small>Net VAT position</small><strong class="{{ $netVat>=0?'kpi-bad':'kpi-good' }}">AED {{ number_format($netVat,2) }}</strong><em>{{ $netVat>=0?'Payable to FTA':'Recoverable' }}</em></div>
<div class="stat"><small>Taxable sales</small><strong>AED {{ number_format($taxableSales,2) }}</strong></div>
<div class="stat"><small>Taxable expenses</small><strong>AED {{ number_format($taxableExpenses,2) }}</strong></div>
</div>
<p class="muted" style="margin-top:10px">This report is a read-only summary of already-posted invoice and expense figures — it does not recalculate or alter any historical VAT amount.</p>
@endsection
