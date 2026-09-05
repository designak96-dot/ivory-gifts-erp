@extends('layouts.app')
@section('title','Delivery Finance Settings')
@section('subtitle','Editable default rates — changes apply from the effective date forward, never retroactively')
@section('content')

<div class="card">
<form method="post" action="{{ route('delivery-finance-settings.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
@csrf
<label>Setting<select name="setting_key" required>@foreach($keys as $k=>$label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
<label>New Value (AED)<input type="number" step="0.01" min="0" name="value" required></label>
<label>Effective From<input type="date" name="effective_date" value="{{ today()->toDateString() }}" required></label>
<button class="btn primary">Save New Rate</button>
</form>
</div>

@foreach($keys as $key=>$label)
<div class="card" style="margin-top:15px">
<h3>{{ $label }}</h3>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Value</th><th>Effective From</th><th>Set By</th></tr></thead><tbody>
@forelse($history[$key] as $h)<tr><td class="amount"><b>AED {{ number_format($h->value,2) }}</b></td><td>{{ $h->effective_date->format('d M Y') }}</td><td>{{ optional(\App\Models\User::find($h->created_by))->name?:'System default' }}</td></tr>@empty<tr><td colspan="3" class="empty">No history yet.</td></tr>@endforelse
</tbody></table></div>
</div>
@endforeach
@endsection
