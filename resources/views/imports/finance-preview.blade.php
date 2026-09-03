@extends('layouts.app')
@section('title','Finance Import Preview — '.$typeLabel)
@section('content')

@if($duplicateWarning)
<div class="card" style="border-color:var(--amber)"><b>⚠ Possible duplicate:</b> {{ $duplicateWarning }}</div>
@endif

<div class="card" style="margin-top:12px">
<h2>Preview — {{ $typeLabel }}</h2>
<div class="grid cols-4" style="margin-top:15px">
<div class="stat"><small>Total Source Rows</small><strong>{{ $preview['total_rows'] }}</strong></div>
<div class="stat"><small>Purchase/Invoice Groups</small><strong>{{ $preview['material_purchase_groups'] }}</strong></div>
<div class="stat"><small>Expense/Income Rows</small><strong>{{ $preview['expense_income_rows'] }}</strong></div>
<div class="stat"><small>Total Before VAT</small><strong>AED {{ number_format($preview['total_ex_tax'],2) }}</strong></div>
</div>

@if(count($preview['new_suppliers']))
<h3 style="margin-top:15px">New Suppliers to be Created</h3>
<p>{{ implode(', ', $preview['new_suppliers']) }}</p>
@endif
@if(count($preview['existing_matched_suppliers']))
<h3 style="margin-top:15px">Existing Suppliers Matched (reused, not duplicated)</h3>
<p>{{ implode(', ', $preview['existing_matched_suppliers']) }}</p>
@endif

@if(count($preview['warnings']))
<h3 style="margin-top:15px;color:var(--amber)">⚠ Warnings — review before committing</h3>
<ul>@foreach($preview['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
@endif

@if(count($preview['fatal_errors']))
<h3 style="margin-top:15px;color:var(--red)">✕ Fatal Errors — must be fixed before import</h3>
<ul>@foreach($preview['fatal_errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif
</div>

@if($preview['can_commit'])
<div class="card" style="margin-top:18px">
<h2>Confirm Import</h2>

@if(count($unmappedMethods))
<h3 style="margin-top:10px">Unresolved Payment Methods</h3>
<p class="muted">These payment method values from your file aren't recognized. Map each to Cash, Bank, Card, or leave as Migration Clearing (a temporary holding account you'll reconcile later) — never silently assumed to be Cash.</p>
@endif

<form method="post" action="{{ route('imports.finance.commit') }}" style="margin-top:12px">
@csrf
@foreach($unmappedMethods as $method)
<label>"{{ $method ?: '(blank)' }}" maps to<select name="payment_method_map[{{ $method }}]">
<option value="migration_clearing">Migration Clearing (default)</option>
<option value="cash">Cash</option>
<option value="bank">Bank</option>
<option value="card">Card</option>
</select></label>
@endforeach

<label style="display:flex;align-items:center;gap:8px;margin-top:15px"><input type="checkbox" name="reviewed_confirmation" value="1"> I reviewed the preview and understand that this import will create accounting records.</label>

<div class="actions" style="margin-top:12px">
<button type="submit" formaction="{{ route('imports.finance.dry-run') }}" class="btn">Run dry-run (writes nothing)</button>
<button type="submit" class="btn primary" onclick="return confirm('This will create real accounting records. Continue?')">Confirm Import</button>
</div>
</form>
</div>
@endif

@endsection
