@extends('layouts.app')
@section('title','Import customers or orders')
@section('content')
<div class="card">
<h2>Import customers, current orders, or historical orders</h2>
<p class="muted">CSV or JSON only — XLSX is not supported in this environment. Export XLSX to CSV first.</p>
<form method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
@csrf
<label>Type<select name="type"><option value="customers">Customers</option><option value="current_orders">Current orders (active, in today's workflow)</option><option value="orders">Historical orders (past/completed, record-keeping only)</option></select></label>
<label>File (CSV or JSON)<input type="file" name="file" accept=".csv,.json" required></label>
<button class="btn primary">Preview</button>
</form>
</div>

<div class="card" style="margin-top:18px"><h2>Downloadable Templates</h2><div class="table-wrap" style="margin-top:12px"><table>
<tr><td>Customers</td><td><a href="{{ route('imports.template','customers') }}">Download CSV</a></td></tr>
<tr><td>Current Orders</td><td><a href="{{ route('imports.template','current_orders') }}">Download CSV</a></td></tr>
<tr><td>Historical Orders</td><td><a href="{{ route('imports.template','orders') }}">Download CSV</a></td></tr>
</table></div></div>
@endsection
