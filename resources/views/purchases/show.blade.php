@extends('layouts.app')
@section('title', $po->purchase_order_number)
@section('subtitle', 'Purchase order detail and goods receipt')
@section('content')
<div class="toolbar"><div><span class="badge {{ $po->status==='received'?'green':($po->status==='partially_received'?'blue':'amber') }}">{{ str($po->status)->replace('_',' ') }}</span></div>
<div style="display:flex;gap:8px">
@if($po->status==='draft'&&auth()->user()->hasPermission('purchases.manage'))<form method="post" action="{{ route('purchases.approve',$po) }}">@csrf<button class="btn primary">Approve</button></form>@endif
@if($po->status==='approved'&&auth()->user()->hasPermission('purchases.manage'))<form method="post" action="{{ route('purchases.mark-ordered',$po) }}">@csrf<button class="btn primary">Mark as ordered</button></form>@endif
<a class="btn" href="{{ route('purchases.index') }}">Back to purchases</a>
</div></div>

<div class="grid cols-2">
<div class="card"><h2>Details</h2><p><b>Supplier</b><br>{{ $po->supplier->name }} ({{ $po->supplier->supplier_code }})</p><p><b>Order date</b><br>{{ $po->order_date->format('d M Y') }}</p><p><b>Expected delivery</b><br>{{ $po->expected_delivery_date?->format('d M Y')??'Not set' }}</p>@if($po->notes)<p><b>Notes</b><br>{{ $po->notes }}</p>@endif</div>
<div class="card"><h2>Totals</h2><p>Subtotal AED {{ number_format($po->subtotal,2) }}<br>VAT AED {{ number_format($po->tax_total,2) }}<br><b>Total AED {{ number_format($po->grand_total,2) }}</b></p></div>
</div>

<div class="card" style="margin-top:18px"><h2>Items</h2>
@if(in_array($po->status,['ordered','partially_received'])&&auth()->user()->hasPermission('inventory.manage'))
<form method="post" action="{{ route('purchases.receive',$po) }}"><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Product</th><th>Ordered</th><th>Received so far</th><th>Remaining</th><th>Unit cost</th><th>Receive now</th></tr></thead><tbody>@foreach($po->items as $i)@php($remaining=(float)$i->qty-(float)$i->qty_received)<tr><td>{{ $i->product?->name_en??$i->description }}<br><span class="muted">{{ $i->product?->sku }}</span></td><td>{{ number_format($i->qty,3) }}</td><td>{{ number_format($i->qty_received,3) }}</td><td class="{{ $remaining>0?'kpi-bad':'kpi-good' }}">{{ number_format($remaining,3) }}</td><td>AED {{ number_format($i->unit_cost,2) }}</td><td><input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $i->id }}"><input type="number" step=".001" min="0" max="{{ $remaining }}" name="items[{{ $loop->index }}][qty_received_now]" value="0" @if($remaining<=0) disabled @endif></td></tr>@endforeach</tbody></table></div>@csrf<div class="actions"><button class="btn primary" data-confirm="Post this goods receipt and update inventory?">Receive stock</button></div></form>
@else
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th>Line total</th></tr></thead><tbody>@foreach($po->items as $i)<tr><td>{{ $i->product?->name_en??$i->description }}<br><span class="muted">{{ $i->product?->sku }}</span></td><td>{{ number_format($i->qty,3) }}</td><td>{{ number_format($i->qty_received,3) }}</td><td>AED {{ number_format($i->unit_cost,2) }}</td><td class="amount">AED {{ number_format($i->line_total,2) }}</td></tr>@endforeach</tbody></table></div>
@endif
</div>
@endsection
