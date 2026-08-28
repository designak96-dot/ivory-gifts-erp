@extends('layouts.app')
@section('title','Import preview')
@section('content')
<div class="card">
<h2>Preview — {{ $preview['total'] }} products found</h2>
@if(count($preview['missing_images']))
<p class="warning">{{ count($preview['missing_images']) }} product(s) reference an image that was not found in the upload.</p>
@endif
<table>
<tr><th>Name</th><th>Category</th><th>Price</th><th>Action</th><th>Image</th></tr>
@foreach($preview['rows'] as $row)
<tr>
<td>{{ $row['name'] }}</td>
<td>{{ $row['category'] }}</td>
<td>{{ number_format($row['price'],2) }}</td>
<td>{{ $row['action'] }}</td>
<td>{{ $row['image_found'] ? 'Found' : ($row['image_basename'] ? 'Missing' : '—') }}</td>
</tr>
@endforeach
</table>
<form method="post" action="{{ route('products.import.dry-run') }}">@csrf<button class="btn">Run dry-run (writes nothing)</button></form>
<form method="post" action="{{ route('products.import.commit') }}">@csrf<button class="btn primary" onclick="return confirm('This will create/update products in the live catalogue. Continue?')">Confirm import</button></form>
</div>
@endsection
