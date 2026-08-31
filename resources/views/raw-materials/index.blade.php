@extends('layouts.app')
@section('title','Raw Materials')
@section('subtitle','Materials used in production — kept separate from Sales Products')
@section('content')

@if(auth()->user()->hasPermission('purchases.manage'))
<div class="card">
<h2>Add Raw Material</h2>
<form method="post" action="{{ route('raw-materials.store') }}" style="margin-top:15px">
@csrf
<div class="form-grid">
<label>Material Name<input name="name" required></label>
<label>Code<input name="code" required placeholder="e.g. MAT-001"></label>
<label>Category<input name="category" placeholder="e.g. Sheets, Adhesives"></label>
<label>Unit<input name="unit" required placeholder="e.g. kg, sheet, roll"></label>
<label>Reorder Level<input type="number" step="0.001" min="0" name="reorder_level" value="0"></label>
<label>Preferred Supplier<select name="preferred_supplier_id"><option value="">None</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
</div>
<div class="actions"><button class="btn primary">Add Material</button></div>
</form>
</div>
@endif

<div class="card" style="margin-top:18px">
<div class="toolbar"><h2>Materials</h2><form><input name="q" value="{{ request('q') }}" placeholder="Search name or code"><button class="btn">Search</button></form></div>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Current Stock</th><th>Reorder Level</th><th>Latest Cost</th><th>Preferred Supplier</th><th>Purchases</th></tr></thead><tbody>
@forelse($materials as $m)
<tr @class(['kpi-bad-row'=>$m->current_stock<=$m->reorder_level])>
<td><a href="{{ route('raw-materials.show',$m) }}"><b>{{ $m->code }}</b></a></td>
<td>{{ $m->name }}</td>
<td>{{ $m->category?:'—' }}</td>
<td class="amount">{{ number_format($m->current_stock,3) }} {{ $m->unit }} @if($m->current_stock<=$m->reorder_level)<span class="badge red" style="margin-left:6px">Low</span>@endif</td>
<td class="amount">{{ number_format($m->reorder_level,3) }}</td>
<td class="amount">{{ $m->latest_cost>0?'AED '.number_format($m->latest_cost,4):'—' }}</td>
<td>{{ $m->preferredSupplier?->name?:'—' }}</td>
<td><a href="{{ route('raw-materials.show',$m) }}" class="btn small">{{ $m->purchases_count }} purchases</a></td>
</tr>
@empty
<tr><td colspan="8" class="empty">No raw materials added yet.</td></tr>
@endforelse
</tbody></table></div>{{ $materials->links() }}
</div>

@endsection
