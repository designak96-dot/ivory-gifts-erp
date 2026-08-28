@extends('layouts.app')
@section('title','Import history')
@section('content')
<div class="card">
<h2>Product import history</h2>
<table>
<tr><th>Date</th><th>Mode</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Errors</th><th>Missing images</th></tr>
@foreach($imports as $import)
<tr>
<td>{{ $import->created_at->format('d M Y H:i') }}</td>
<td>{{ $import->is_dry_run ? 'Dry run' : 'Committed' }}</td>
<td>{{ $import->created_count }}</td>
<td>{{ $import->updated_count }}</td>
<td>{{ $import->skipped_count }}</td>
<td>{{ $import->error_count }}</td>
<td>{{ $import->missing_image_count }}</td>
</tr>
@endforeach
</table>
{{ $imports->links() }}
</div>
@endsection
