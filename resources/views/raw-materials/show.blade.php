@extends('layouts.app')
@section('title',$material->name)
@section('subtitle',$material->code.' · '.($material->category?:'Raw material'))
@section('content')

<p><a href="{{ route('raw-materials.index') }}">&larr; Back to Purchases &amp; Suppliers</a></p>

<div class="grid cols-4">
<div class="stat"><small>Current Stock</small><strong>{{ number_format($material->current_stock,3) }} {{ $material->unit }}</strong></div>
<div class="stat"><small>Reorder Level</small><strong>{{ number_format($material->reorder_level,3) }} {{ $material->unit }}</strong></div>
<div class="stat"><small>Latest Cost</small><strong>{{ $priceHistory['latest']!==null?'AED '.number_format($priceHistory['latest'],4):'—' }}</strong></div>
<div class="stat"><small>Preferred Supplier</small><strong>{{ $material->preferredSupplier?->name?:'—' }}</strong></div>
</div>

<div class="card" style="margin-top:18px"><h2>Price History</h2>
@if($priceHistory['latest']===null)
<p class="muted" style="margin-top:12px">No purchases recorded yet.</p>
@else
<div class="grid cols-4" style="margin-top:15px">
<div class="stat"><small>Previous Price</small><strong>{{ $priceHistory['previous']!==null?'AED '.number_format($priceHistory['previous'],4):'—' }}</strong></div>
<div class="stat"><small>Latest Price</small><strong>AED {{ number_format($priceHistory['latest'],4) }}</strong></div>
<div class="stat"><small>Lowest Price</small><strong class="kpi-good">AED {{ number_format($priceHistory['lowest'],4) }}</strong></div>
<div class="stat"><small>Highest Price</small><strong class="kpi-bad">AED {{ number_format($priceHistory['highest'],4) }}</strong></div>
</div>
@if($priceHistory['change_percent']!==null)
<p style="margin-top:12px">
<b>Change vs previous purchase:</b>
<span class="badge {{ $priceHistory['change_percent']>0?'red':($priceHistory['change_percent']<0?'green':'') }}">
{{ $priceHistory['change_percent']>0?'▲ +':($priceHistory['change_percent']<0?'▼ ':'') }}{{ number_format($priceHistory['change_percent'],1) }}%
</span>
</p>
@endif

@if(count($priceHistory['by_supplier'])>1)
<h3 style="margin-top:18px">Compare Supplier Prices</h3>
<div class="table-wrap" style="margin-top:10px"><table><thead><tr><th>Supplier</th><th>Latest Price</th><th>Purchases</th></tr></thead><tbody>
@foreach($priceHistory['by_supplier'] as $row)
<tr><td>{{ $row['supplier']->name }}</td><td class="amount">AED {{ number_format($row['latest_price'],4) }}</td><td>{{ $row['purchase_count'] }}</td></tr>
@endforeach
</tbody></table></div>
@endif
@endif
</div>

<div class="card" style="margin-top:18px"><h2>Purchase History — {{ $material->name }}</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Purchase</th><th>Date</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>VAT</th><th>Line Total</th></tr></thead><tbody>
@forelse($purchaseLines as $line)
<tr>
<td><b>{{ $line->purchase->purchase_number }}</b></td>
<td>{{ $line->purchase->purchase_date->format('d M Y') }}</td>
<td>{{ $line->purchase->supplier->name }}</td>
<td class="amount">{{ number_format($line->quantity,3) }} {{ $line->unit }}</td>
<td class="amount">AED {{ number_format($line->unit_price,4) }}</td>
<td class="amount">AED {{ number_format($line->tax_amount,2) }}</td>
<td class="amount"><b>AED {{ number_format($line->line_total,2) }}</b></td>
</tr>
@empty
<tr><td colspan="7" class="empty">No purchases recorded yet.</td></tr>
@endforelse
</tbody></table></div>{{ $purchaseLines->links() }}</div>

@endsection
