@extends('layouts.app')
@section('title',$product->exists?'Edit Product':'New Product')
@section('subtitle','Product name, price and costing details — leave SKU blank to auto-generate')
@section('content')
<form method="post" action="{{ $product->exists?route('products.update',$product):route('products.store') }}" enctype="multipart/form-data">@csrf @if($product->exists)@method('put')@endif
<div class="card"><div class="form-grid">
@include('products._fields', ['product' => $product, 'categories' => $categories, 'taxRates' => $taxRates])
</div><div class="actions"><a class="btn" href="{{ route('products.index') }}">Cancel</a><button class="btn primary">Save product</button></div></div></form>
@endsection
