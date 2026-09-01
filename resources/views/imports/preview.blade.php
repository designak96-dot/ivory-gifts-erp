@extends('layouts.app')
@section('title','Import preview')
@section('content')
<div class="card">
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
<form method="post" action="{{ route('imports.dry-run') }}">@csrf<button class="btn">Run dry-run (writes nothing)</button></form>
<form method="post" action="{{ route('imports.commit') }}">@csrf<button class="btn primary" onclick="return confirm('This will create/update records in the live database. Conflicts are never overwritten. Continue?')">Confirm import</button></form>
</div>
@endsection
