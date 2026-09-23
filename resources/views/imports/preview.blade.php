@extends('layouts.app')
@section('title','Import preview')
@section('content')
<div class="card">
@if(!empty($duplicateWarning))<div class="alert danger">{{ $duplicateWarning }}</div>@endif
@if($type === 'orders')
  @php($c = $preview['counts'] ?? [])
  <h2>Preview — {{ $preview['total'] }} orders @if(!empty($preview['sheet_month']))for {{ \Carbon\Carbon::parse($preview['sheet_month'].'-01')->format('F Y') }}@endif</h2>
  <p class="muted">
    {{ $c['create'] ?? 0 }} to create · {{ $c['update'] ?? 0 }} to update · <b>{{ $c['skip'] ?? 0 }} will be skipped</b>
    · Total AED {{ number_format($preview['grand_total'], 2) }} · Paid AED {{ number_format($preview['paid_total'], 2) }}
    · {{ $preview['source_row_count'] }} file rows ({{ $preview['ignored_rows'] }} blank lines ignored@if($preview['orphan_rows']), {{ $preview['orphan_rows'] }} item lines before the first order number ignored@endif)
  </p>
  <div class="table-wrap"><table>
  <tr><th>#</th><th>Customer</th><th>Phone</th><th>Items</th><th>Delivery</th><th>Emirate</th><th>Total</th><th>Paid</th><th>Action</th><th>Notes</th></tr>
  @foreach($preview['rows'] as $row)
  <tr>
    <td>{{ $row['source_order_number'] }}</td>
    <td>{{ $row['customer'] }}</td>
    <td>{{ $row['phone'] ?? '—' }}</td>
    <td>{{ $row['line_count'] }}</td>
    <td>{{ $row['delivery_date'] ?? '—' }}@if(($row['fulfillment_type'] ?? '') === 'pickup') <small class="muted">(pickup)</small>@endif</td>
    <td>{{ $row['emirate'] ?? '—' }}</td>
    <td>{{ number_format($row['grand_total'], 2) }}</td>
    <td>{{ number_format($row['paid'], 2) }}</td>
    <td><span class="badge {{ $row['action']==='skip'?'red':($row['action']==='create'?'green':'amber') }}">{{ $row['action'] }}</span></td>
    <td><small class="muted">{{ implode(' ', $row['issues'] ?? []) }}</small></td>
  </tr>
  @endforeach
  </table></div>
@else
  <h2>Preview — {{ $preview['total'] }} rows</h2>
  <table>
  <tr><th>Reference</th><th>Name/Customer</th><th>Action</th></tr>
  @foreach($preview['rows'] as $row)
  <tr>
  <td>{{ $row['source_id'] ?? $row['source_order_number'] ?? $row['manual_reference'] ?? '—' }}</td>
  <td>{{ $row['name'] ?? $row['customer'] ?? '' }}</td>
  <td><span class="badge {{ $row['action']==='conflict'?'red':($row['action']==='create'?'green':'amber') }}">{{ $row['action'] }}</span></td>
  </tr>
  @endforeach
  </table>
@endif
<div style="display:flex;gap:10px;margin-top:14px">
<form method="post" action="{{ route('imports.dry-run') }}">@csrf<button class="btn">Run dry-run (writes nothing)</button></form>
<form method="post" action="{{ route('imports.commit') }}">@csrf<button class="btn primary" onclick="return confirm('This will create/update records in the live database. Conflicts are never overwritten. Continue?')">Confirm import</button></form>
</div>
</div>
@endsection
