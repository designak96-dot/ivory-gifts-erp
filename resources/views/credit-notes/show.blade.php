@extends('layouts.app')
@section('title',$creditNote->credit_note_number)
@section('subtitle','Credit note detail')
@section('content')
<div class="grid cols-3">
<div class="card"><h2>Customer</h2><p><a href="{{ route('customers.show',$creditNote->customer) }}">{{ $creditNote->customer->name }}</a></p></div>
<div class="card"><h2>Linked to</h2><p>@if($creditNote->invoice)Invoice <a href="{{ route('invoices.show',$creditNote->invoice) }}">{{ $creditNote->invoice->invoice_number }}</a>@else No invoice linked @endif@if($creditNote->salesOrder)<br>Order <a href="{{ route('orders.show',$creditNote->salesOrder) }}">{{ $creditNote->salesOrder->order_number }}</a>@endif</p></div>
<div class="card"><h2>Totals</h2><p>Subtotal AED {{ number_format($creditNote->subtotal,2) }}<br>VAT AED {{ number_format($creditNote->tax_total,2) }}<br><b>Total AED {{ number_format($creditNote->grand_total,2) }}</b></p></div>
</div>
<div class="card" style="margin-top:18px"><h2>Reason</h2><p>{{ $creditNote->reason }}</p></div>
<div class="card" style="margin-top:18px"><h2>Items</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>VAT</th><th>Total</th></tr></thead><tbody>@foreach($creditNote->items as $i)<tr><td>{{ $i->description }}</td><td>{{ number_format($i->qty,2) }}</td><td>AED {{ number_format($i->unit_price,2) }}</td><td>AED {{ number_format($i->tax_amount,2) }}</td><td class="amount">AED {{ number_format($i->line_total,2) }}</td></tr>@endforeach</tbody></table></div></div>
<p class="muted" style="margin-top:12px">Posted by {{ $creditNote->creator?->name??'System' }} on {{ $creditNote->created_at->format('d M Y, h:i A') }}</p>
@endsection
