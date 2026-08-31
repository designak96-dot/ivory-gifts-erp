@extends('layouts.app')
@section('title','Credit Notes')
@section('subtitle','Refunds and credits issued against invoices')
@section('content')
<div class="toolbar"><div></div>@if(auth()->user()->hasPermission('invoices.manage'))<a class="btn primary" href="{{ route('credit-notes.create') }}">New credit note</a>@endif</div>
<div class="table-wrap"><table><thead><tr><th>Credit note</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Reason</th><th>Total</th></tr></thead><tbody>
@forelse($creditNotes as $cn)
<tr><td><a href="{{ route('credit-notes.show',$cn) }}"><b>{{ $cn->credit_note_number }}</b></a></td><td>{{ $cn->customer->name }}</td><td>{{ $cn->invoice?->invoice_number??'—' }}</td><td>{{ $cn->credit_date->format('d M Y') }}</td><td>{{ \Illuminate\Support\Str::limit($cn->reason,40) }}</td><td class="amount kpi-bad">AED {{ number_format($cn->grand_total,2) }}</td></tr>
@empty
<tr><td colspan="6" class="empty">No credit notes yet.</td></tr>
@endforelse
</tbody></table></div>{{ $creditNotes->links() }}
@endsection
