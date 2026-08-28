@extends('layouts.app')
@section('title','Duplicate customers')
@section('subtitle','Grouped by matching phone number — nothing here is deleted or merged automatically')
@section('content')
<div class="card">
<p>{{ $duplicates->count() }} phone number(s) shared by more than one customer record. Review each group and manually consolidate if appropriate — related orders, invoices, and payments are shown so you can judge which record to keep before acting.</p>
</div>
@forelse($duplicates as $group)
<div class="card" style="margin-top:14px">
<h2>{{ $group->first()->phone }}</h2>
<table>
<tr><th>Customer</th><th>Code</th><th>Created</th><th>Orders</th><th>Invoices</th><th></th></tr>
@foreach($group as $customer)
<tr>
<td>{{ $customer->name }}{{ $customer->company_name?' · '.$customer->company_name:'' }}</td>
<td>{{ $customer->customer_code }}</td>
<td>{{ $customer->created_at->format('d M Y') }}</td>
<td>{{ $customer->orders_count }}</td>
<td>{{ $customer->invoices_count }}</td>
<td><a class="btn small" href="{{ route('customers.show',$customer) }}">Open</a></td>
</tr>
@endforeach
</table>
</div>
@empty
<div class="card" style="margin-top:14px"><p class="muted">No duplicate phone numbers found among current customers.</p></div>
@endforelse
@endsection
