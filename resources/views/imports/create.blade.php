@extends('layouts.app')
@section('title','Import customers or orders')
@section('content')
<div class="card">
<h2>Import historical customers or orders</h2>
<p class="muted">CSV or JSON only — XLSX is not supported in this environment. Export XLSX to CSV first.</p>
<form method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
@csrf
<label>Type<select name="type"><option value="customers">Customers</option><option value="orders">Historical orders</option></select></label>
<label>File (CSV or JSON)<input type="file" name="file" accept=".csv,.json" required></label>
<button class="btn primary">Preview</button>
</form>
</div>
@endsection
