@extends('layouts.app')
@section('title','Finance Migration Import')
@section('content')
<div class="card">
<h2>Finance & Order Migration Import</h2>
<p class="muted">CSV or JSON only — XLSX is not supported in this environment. Export XLSX to CSV first.</p>
<p class="muted" style="margin-top:8px">This creates real accounting records — Owner/Admin access only. Every import shows a Preview before anything is written, and nothing is committed without your explicit confirmation.</p>
<form method="post" action="{{ route('imports.finance.preview') }}" enctype="multipart/form-data" style="margin-top:15px">
@csrf
<label>Type<select name="type" required>
@foreach($types as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
</select></label>
<label>File (CSV or JSON)<input type="file" name="file" accept=".csv,.json" required></label>
<button class="btn primary">Preview</button>
</form>
</div>

<div class="card" style="margin-top:18px"><h2>Downloadable Templates</h2><div class="table-wrap" style="margin-top:12px"><table>
<tr><td>Material Purchases</td><td><a href="{{ route('imports.finance.template','material_purchases') }}">Download CSV</a></td></tr>
<tr><td>General Expenses</td><td><a href="{{ route('imports.finance.template','general_expenses') }}">Download CSV</a></td></tr>
<tr><td>Salaries</td><td><a href="{{ route('imports.finance.template','salaries') }}">Download CSV</a></td></tr>
<tr><td>Rent Expenses</td><td><a href="{{ route('imports.finance.template','rent_expenses') }}">Download CSV</a></td></tr>
<tr><td>Other Income</td><td><a href="{{ route('imports.finance.template','other_income') }}">Download CSV</a></td></tr>
<tr><td>Ivory Delivery Income</td><td><a href="{{ route('imports.finance.template','ivory_delivery_income') }}">Download CSV</a></td></tr>
<tr><td>iFast Delivery Income</td><td><a href="{{ route('imports.finance.template','ifast_delivery_income') }}">Download CSV</a></td></tr>
</table></div></div>
@endsection
