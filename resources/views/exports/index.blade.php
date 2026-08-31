@extends('layouts.app')
@section('title','Export Center')
@section('subtitle','Download data as CSV — opens directly in Excel or any spreadsheet tool')
@section('content')
<p class="muted">XLSX and PDF export are not available in this build — this environment has no spreadsheet/PDF library installed and cannot safely add one without a controlled update. CSV is fully supported and opens directly in Excel.</p>

<h2 style="margin-top:20px">Data exports</h2>
<div class="grid cols-4" style="margin-top:12px">
<a class="btn" href="{{ route('exports.orders') }}">Sales Orders</a>
<a class="btn" href="{{ route('exports.invoices') }}">Invoices</a>
<a class="btn" href="{{ route('exports.payments') }}">Payments</a>
<a class="btn" href="{{ route('exports.customers') }}">Customers</a>
<a class="btn" href="{{ route('exports.products') }}">Products</a>
<a class="btn" href="{{ route('exports.expenses') }}">Expenses</a>
<a class="btn" href="{{ route('exports.purchases') }}">Purchases</a>
<a class="btn" href="{{ route('vat.export') }}">VAT Report</a>
</div>

<h2 style="margin-top:25px">Reports</h2>
<div class="grid cols-4" style="margin-top:12px">
<a class="btn" href="{{ route('exports.orders') }}">Monthly Sales</a>
<a class="btn" href="{{ route('exports.profit') }}">Profit Report</a>
<a class="btn" href="{{ route('exports.expenses') }}">Expenses</a>
<a class="btn" href="{{ route('exports.outstanding') }}">Outstanding</a>
<a class="btn" href="{{ route('vat.export') }}">VAT</a>
<a class="btn" href="{{ route('exports.top-customers') }}">Top Customers</a>
<a class="btn" href="{{ route('exports.top-products') }}">Top Products</a>
</div>
@endsection
