@extends('layouts.app')
@section('title','Tasks')
@section('subtitle','Reminders and follow-ups linked to your work')
@section('content')
<div class="toolbar">
<div class="filters">
<a class="badge {{ !request('filter') && !request('status') ?'blue':'' }}" href="{{ route('tasks.index') }}">All</a>
<a class="badge {{ request('filter')==='mine'?'blue':'' }}" href="{{ route('tasks.index',['filter'=>'mine']) }}">Assigned to me</a>
<a class="badge {{ request('filter')==='overdue'?'red':'' }}" href="{{ route('tasks.index',['filter'=>'overdue']) }}">Overdue</a>
<a class="badge {{ request('status')==='open'?'blue':'' }}" href="{{ route('tasks.index',['status'=>'open']) }}">Open</a>
<a class="badge {{ request('status')==='done'?'green':'' }}" href="{{ route('tasks.index',['status'=>'done']) }}">Done</a>
</div>
</div>

<div class="card"><h2>New task</h2>
<form method="post" action="{{ route('tasks.store') }}" style="margin-top:12px"><div class="form-grid">
<label>Task<input name="title" required placeholder="e.g. Call customer tomorrow"></label>
<label>Assigned to<select name="assigned_to"><option value="">Unassigned</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
<label>Due date/time<input type="datetime-local" name="due_at"></label>
<label>Priority<select name="priority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
<label class="span-2">Notes<textarea name="description" placeholder="Optional details"></textarea></label>
<label class="check"><input type="checkbox" name="reminder_enabled" value="1"> Show as a reminder in notifications when due soon</label>
</div><div class="actions"><button class="btn primary">Add task</button></div></form>
</div>

<div class="card" style="margin-top:18px"><h2>Tasks</h2>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Task</th><th>Linked to</th><th>Assigned</th><th>Due</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($tasks as $t)
<tr><td><b>{{ $t->title }}</b>@if($t->description)<br><span class="muted">{{ \Illuminate\Support\Str::limit($t->description,60) }}</span>@endif</td>
<td>@if($t->linkable)<span class="badge blue">{{ class_basename($t->linkable_type) }}</span> {{ $t->linkable->name??$t->linkable->order_number??$t->linkable->invoice_number??$t->linkable->name_en??'#'.$t->linkable_id }}@else—@endif</td>
<td>{{ $t->assignee?->name??'Unassigned' }}</td>
<td class="{{ $t->is_overdue?'kpi-bad':'' }}">{{ $t->due_at?->format('d M Y, h:i A')??'—' }}@if($t->is_overdue) (overdue)@endif</td>
<td><span class="badge {{ $t->priority==='urgent'?'red':($t->priority==='high'?'amber':'blue') }}">{{ ucfirst($t->priority) }}</span></td>
<td><form method="post" action="{{ route('tasks.update',$t) }}" style="display:inline">@csrf @method('patch')<select name="status" onchange="this.form.submit()"><option value="open" @selected($t->status==='open')>Open</option><option value="in_progress" @selected($t->status==='in_progress')>In progress</option><option value="done" @selected($t->status==='done')>Done</option><option value="cancelled" @selected($t->status==='cancelled')>Cancelled</option></select></form></td>
<td>@if($t->created_by===auth()->id()||auth()->user()->hasPermission('orders.delete'))<form method="post" action="{{ route('tasks.destroy',$t) }}" data-confirm="Delete this task?">@csrf @method('delete')<button type="submit" class="link">Delete</button></form>@endif</td>
</tr>
@empty
<tr><td colspan="7" class="empty">No tasks yet.</td></tr>
@endforelse
</tbody></table></div>{{ $tasks->links() }}
</div>
@endsection
