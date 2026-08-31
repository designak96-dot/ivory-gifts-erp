@extends('layouts.app')
@section('title',$product->exists?'Edit Product':'New Product')
@section('subtitle','Product name, price and costing details — leave SKU blank to auto-generate')
@section('content')
<form method="post" action="{{ $product->exists?route('products.update',$product):route('products.store') }}" enctype="multipart/form-data">@csrf @if($product->exists)@method('put')@endif
<div class="card"><div class="form-grid">
@include('products._fields', ['product' => $product, 'categories' => $categories, 'taxRates' => $taxRates])
</div><div class="actions"><a class="btn" href="{{ route('products.index') }}">Cancel</a><button class="btn primary">Save product</button></div></div></form>
@if($product->exists && $priceHistory->count())
<div class="card" style="margin-top:18px"><h2>Supplier price history</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Supplier</th><th>Previous price</th><th>Latest price</th><th>Lowest price</th><th>Latest purchase date</th></tr></thead><tbody>@foreach($priceHistory as $row)<tr><td><b>{{ $row['supplier']->name }}</b></td><td>{{ $row['previous_price']!==null?'AED '.number_format($row['previous_price'],2):'—' }}</td><td class="amount">AED {{ number_format($row['latest_price'],2) }}</td><td class="kpi-good">AED {{ number_format($row['lowest_price'],2) }}</td><td>{{ $row['latest_date']->format('d M Y') }}</td></tr>@endforeach</tbody></table></div></div>
@endif
@endsection
