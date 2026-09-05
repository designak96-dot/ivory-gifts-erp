@extends('layouts.app')
@section('title','Vehicle Expenses')
@section('content')
@if(auth()->user()->hasPermission('vehicle-expenses.manage'))
<div class="card">
<h2>Record Vehicle Expense</h2>
<form method="post" action="{{ route('vehicle-expenses.store') }}" enctype="multipart/form-data" style="margin-top:10px">
@csrf
<div class="form-grid">
<label>Type<select name="expense_type" required><option value="petrol">Petrol</option><option value="maintenance">Maintenance</option><option value="repair">Repair</option><option value="tyres">Tyres</option><option value="registration">Registration</option><option value="insurance">Insurance</option><option value="car_wash">Car Wash</option><option value="parking">Parking</option><option value="toll">Toll</option><option value="other">Other</option></select></label>
<label>Date<input type="date" name="expense_date" value="{{ today()->toDateString() }}" required></label>
<label>Vehicle<select name="vehicle_id"><option value="">--</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></label>
<label>Driver<select name="driver_id"><option value="">--</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
<label>Amount (ex. tax)<input type="number" step="0.01" name="amount_ex_tax" required></label>
<label>Tax<input type="number" step="0.01" name="tax_amount" value="0"></label>
<label>Supplier/Garage<input name="supplier_name"></label>
<label>Invoice Ref<input name="invoice_reference"></label>
<label>Payment Method<select name="payment_method"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></label>
<label>Odometer<input type="number" name="odometer_reading"></label>
<label>Description<input name="description"></label>
<label>Proof<input type="file" name="proof"></label>
</div>
<div class="actions"><button class="btn primary">Save</button></div>
</form>
</div>
@endif

<div class="card" style="margin-top:15px"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Vehicle</th><th>Driver</th><th>Amount</th><th>Supplier</th></tr></thead><tbody>
@forelse($expenses as $e)
<tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ ucfirst(str_replace('_',' ',$e->expense_type)) }}</td><td>{{ $e->vehicle?->name?:'—' }}</td><td>{{ $e->driver?->name?:'—' }}</td><td class="amount"><b>AED {{ number_format($e->total_amount,2) }}</b></td><td>{{ $e->supplier?->name?:'—' }}</td></tr>
@empty<tr><td colspan="6" class="empty">No vehicle expenses recorded yet.</td></tr>@endforelse
</tbody></table></div>{{ $expenses->links() }}</div>
@endsection
