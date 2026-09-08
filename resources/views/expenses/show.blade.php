@extends('layouts.app')
@section('title',$expense->expense_number)
@section('content')

<div class="grid cols-4">
<div class="stat"><small>Total</small><strong>AED {{ number_format($expense->total_amount,2) }}</strong></div>
<div class="stat"><small>Category</small><strong>{{ $expense->category }}</strong></div>
<div class="stat"><small>Paid By</small><strong>{{ $expense->paid_by ? ucfirst($expense->paid_by) : '—' }}</strong></div>
<div class="stat"><small>Reimbursement</small><strong>{{ $expense->reimbursement_status ? ucfirst($expense->reimbursement_status) : '—' }}</strong></div>
</div>

<div class="card" style="margin-top:15px">
<h2>Details</h2>
<div class="form-grid" style="margin-top:8px">
<div><b>Date:</b> {{ $expense->expense_date->format('d M Y') }}</div>
<div><b>Payee:</b> {{ $expense->payee?:'—' }}</div>
<div><b>Vehicle:</b> {{ $expense->vehicle?->name?:'—' }}</div>
<div><b>Driver:</b> {{ $expense->driver?->name?:'—' }}</div>
<div><b>Delivery Day:</b> {{ $expense->delivery_day?->format('d M Y')??'—' }}</div>
<div><b>Reference:</b> {{ $expense->reference?:'—' }}</div>
</div>
</div>

@if($expense->vehicle_id)
<div class="card" style="margin-top:15px">
<h2>Allocate Across Deliveries</h2>
<p class="muted">Analytical only — this never creates a second accounting entry. It only affects delivery profitability reporting.</p>
@if(auth()->user()->hasPermission('vehicle-expenses.manage'))
<form method="post" action="{{ route('expenses.allocate',$expense) }}" style="margin-top:10px">
@csrf
<div class="table-wrap" style="max-height:350px;overflow:auto"><table><thead><tr><th></th><th>Delivery</th><th>Customer</th><th>Completed</th></tr></thead><tbody>
@forelse($candidateDeliveries as $d)
<tr><td><input type="checkbox" name="delivery_ids[]" value="{{ $d->id }}" @checked(in_array($d->id,$currentAllocations))></td><td>{{ $d->delivery_note_number }}</td><td>{{ $d->customer->name }}</td><td>{{ $d->delivered_at?->format('d M Y H:i')??'—' }}</td></tr>
@empty<tr><td colspan="4" class="empty">No own-company deliveries found{{ $expense->delivery_day ? ' for '.$expense->delivery_day->format('d M Y') : '' }}.</td></tr>
@endforelse
</tbody></table></div>
<div class="actions" style="margin-top:10px"><button class="btn primary">Save Allocation</button></div>
</form>
@endif
</div>
@endif

@endsection
