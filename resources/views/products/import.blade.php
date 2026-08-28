@extends('layouts.app')
@section('title','Import products')
@section('content')
<div class="card">
<h2>Bulk product import</h2>
<p>Upload a CSV, a JSON file, or one ZIP containing either plus an image folder. Nothing is written to the catalogue until you confirm after previewing. Existing products are matched and updated by SKU; a new SKU creates a new product.</p>
<p><a href="{{ route('products.import.csv-template') }}">Download CSV template</a> · <a href="{{ route('products.import.json-template') }}">Download JSON example</a></p>
<form method="post" action="{{ route('products.import.preview') }}" enctype="multipart/form-data">
@csrf
<label><input type="radio" name="source" value="csv" checked> CSV file</label>
<label><input type="radio" name="source" value="json"> JSON file</label>
<label><input type="radio" name="source" value="zip"> ZIP (CSV or JSON + images)</label>
<input type="file" name="csv_file" accept=".csv">
<input type="file" name="json_file" accept=".json">
<input type="file" name="zip_file" accept=".zip">
<button class="btn primary" type="submit">Preview import</button>
</form>
</div>
@endsection
