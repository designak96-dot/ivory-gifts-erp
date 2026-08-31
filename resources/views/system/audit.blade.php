@extends('layouts.app') @section('title','Audit Log') @section('subtitle','Append-only record of business data changes')
@section('content')<div class="toolbar"><form><select name="action"><option value="">All actions</option>@foreach(['created','updated','deleted'] as $a)<option @selected(request('action')===$a)>{{ $a }}</option>@endforeach</select><input name="model" value="{{ request('model') }}" placeholder="Model name"><label class="check"><input type="checkbox" name="financial" value="1" @checked(request('financial')) onchange="this.form.submit()"> Financial changes only</label><button class="btn">Filter</button></form></div><div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record type / ID</th><th>Changed values</th><th>IP</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i:s') }}</td><td>{{ $log->user?->name??'System' }}</td><td><span class="badge">{{ $log->action }}</span></td><td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td><td>
@if($log->action==='updated' && is_array($log->old_values) && is_array($log->new_values))
<table style="width:auto"><tbody>
@foreach($log->new_values as $field=>$newVal)
@continue(in_array($field,['updated_at']))
<tr><td style="padding:2px 8px 2px 0;font-weight:700;white-space:nowrap">{{ $field }}</td><td style="padding:2px 8px" class="muted">{{ \Illuminate\Support\Str::limit(is_scalar($log->old_values[$field]??null)?(string)($log->old_values[$field]??'—'):json_encode($log->old_values[$field]??null),40) }}</td><td style="padding:2px 4px">→</td><td style="padding:2px 0" class="kpi-good">{{ \Illuminate\Support\Str::limit(is_scalar($newVal)?(string)$newVal:json_encode($newVal),40) }}</td></tr>
@endforeach
</tbody></table>
@elseif($log->action==='created')
<span class="muted">New record created</span>
@else
<details><summary>View details</summary><pre style="max-width:480px;white-space:pre-wrap">{{ json_encode(['old'=>$log->old_values,'new'=>$log->new_values],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
@endif
</td><td>{{ $log->ip_address?:'—' }}</td></tr>@empty<tr><td colspan="6" class="empty">No audit events.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}@endsection
