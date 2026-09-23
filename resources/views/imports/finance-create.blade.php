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
<label>Month of this sheet<input type="month" name="sheet_month" id="finance_sheet_month" value="{{ old('sheet_month', now()->subMonthNoOverflow()->format('Y-m')) }}" required><small class="muted">Rows with no date are dated the 1st of this month. Rows with a date keep their own date — the preview flags any outside this month.</small></label>
<label>File (CSV or JSON)<input type="file" name="file" id="finance_file" accept=".csv,.json" required></label>
<button class="btn primary">Preview</button>
</form>
<script>
(function () {
  // Shortcut: a file named like "august-expenses.csv" fills the month in.
  var file = document.getElementById('finance_file');
  var month = document.getElementById('finance_sheet_month');
  file.addEventListener('change', function () {
    var name = (file.files[0] && file.files[0].name || '').toLowerCase();
    var names = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    var m = -1; names.forEach(function (n, i) { if (m < 0 && new RegExp('(^|[^a-z])' + n).test(name)) m = i; });
    if (m < 0) return;
    var now = new Date(); var y = (name.match(/20\d\d/) || [])[0];
    y = y ? parseInt(y, 10) : (m > now.getMonth() ? now.getFullYear() - 1 : now.getFullYear());
    month.value = y + '-' + String(m + 1).padStart(2, '0');
  });
})();
</script>
</div>

<div class="card" style="margin-top:18px"><h2>Downloadable Templates</h2><div class="table-wrap" style="margin-top:12px"><table>
<tr><td>Material Purchases</td><td><a href="{{ route('imports.finance.template','material_purchases') }}">Download CSV</a></td></tr>
<tr><td>Expenses (General, Salaries, Rent)</td><td><a href="{{ route('imports.finance.template','expenses') }}">Download CSV</a></td></tr>
<tr><td>Other Income</td><td><a href="{{ route('imports.finance.template','other_income') }}">Download CSV</a></td></tr>
<tr><td>Ivory Delivery Income</td><td><a href="{{ route('imports.finance.template','ivory_delivery_income') }}">Download CSV</a></td></tr>
<tr><td>iFast Delivery Income</td><td><a href="{{ route('imports.finance.template','ifast_delivery_income') }}">Download CSV</a></td></tr>
</table></div></div>
@endsection
