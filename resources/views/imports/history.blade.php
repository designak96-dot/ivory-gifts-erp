@extends('layouts.app')
@section('title','Import history')
@section('content')
<div class="card">
<h2>Customer & order import history</h2>
<table>
<tr><th>Date</th><th>Type</th><th>Mode</th><th>Created</th><th>Updated</th><th>Conflicts</th><th>Errors</th><th></th></tr>
@foreach($imports as $import)
<tr>
<td>{{ $import->created_at->format('d M Y H:i') }}</td>
<td>{{ $import->type }}</td>
<td>{{ $import->is_dry_run ? 'Dry run' : 'Committed' }}</td>
<td>{{ $import->created_count }}</td>
<td>{{ $import->updated_count }}</td>
<td>{{ $import->conflict_count }}</td>
<td>{{ $import->error_count }}</td>
<td>@if($import->conflict_count||$import->error_count)<a class="btn small" href="{{ route('imports.errors',$import) }}">Download issues</a>@endif</td>
</tr>
@endforeach
</table>
{{ $imports->links() }}
</div>
@endsection
