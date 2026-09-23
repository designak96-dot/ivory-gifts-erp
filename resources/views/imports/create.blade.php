@extends('layouts.app')
@section('title','Import customers or orders')
@section('content')
<div class="card">
<h2>Import customers, current orders, or historical orders</h2>
<p class="muted">CSV or JSON only — XLSX is not supported in this environment. Export XLSX to CSV first.</p>
<form method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
@csrf
<label>Type<select name="type"><option value="customers">Customers</option><option value="current_orders">Current orders (active, in today's workflow)</option><option value="orders">Historical orders (past/completed, record-keeping only)</option></select></label>
<label id="sheet-month-field">Month of this order sheet<input type="month" name="sheet_month" id="sheet_month" value="{{ old('sheet_month', now()->subMonthNoOverflow()->format('Y-m')) }}"><small class="muted">Orders without an order date are dated the 1st of this month. Order numbers restart monthly, so the month keeps July #1 and August #1 separate.</small></label>
<label>File (CSV or JSON)<input type="file" name="file" id="import_file" accept=".csv,.json" required></label>
<button class="btn primary">Preview</button>
</form>
<script>
(function () {
  var type = document.querySelector('select[name="type"]');
  var field = document.getElementById('sheet-month-field');
  var month = document.getElementById('sheet_month');
  var file = document.getElementById('import_file');
  function toggle() { var on = type.value === 'orders'; field.style.display = on ? '' : 'none'; month.required = on; }
  type.addEventListener('change', toggle); toggle();
  // Shortcut: a file named like "july.csv" or "aug-2026.csv" fills the month in.
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
<tr><td>Customers</td><td><a href="{{ route('imports.template','customers') }}">Download CSV</a></td></tr>
<tr><td>Current Orders</td><td><a href="{{ route('imports.template','current_orders') }}">Download CSV</a></td></tr>
<tr><td>Historical Orders</td><td><a href="{{ route('imports.template','orders') }}">Download CSV</a></td></tr>
</table></div></div>
@endsection
