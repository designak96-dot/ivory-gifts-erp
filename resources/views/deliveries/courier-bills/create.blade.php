@extends('layouts.app')
@section('title','New Courier Bill')
@section('content')
<div class="card">
<form method="post" action="{{ route('courier-bills.store') }}" enctype="multipart/form-data">
@csrf
<div class="form-grid">
<label>Courier / Supplier<select name="supplier_id"><option value="">-- New courier --</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
<label>Or new courier name<input name="courier_name" placeholder="e.g. Aramex"></label>
<label>Supplier Invoice #<input name="supplier_invoice_number"></label>
<label>Bill Date<input type="date" name="bill_date" value="{{ today()->toDateString() }}" required></label>
<label>Period Start<input type="date" name="period_start" required></label>
<label>Period End<input type="date" name="period_end" required></label>
<label>Currency<input name="currency" value="AED" maxlength="3"></label>
<label>Exchange Rate <span class="muted">(if not AED)</span><input type="number" step="0.000001" name="exchange_rate"></label>
<label>Amount (ex. tax)<input type="number" step="0.01" name="amount_ex_tax" required></label>
<label>Tax Amount<input type="number" step="0.01" name="tax_amount" value="0"></label>
<label>Proof <span class="muted">(bill copy)</span><input type="file" name="proof"></label>
</div>

<h3 style="margin-top:18px">Select Deliveries to Bill</h3>
@error('delivery_ids')<p style="color:var(--red)">{{ $message }}</p>@enderror
<div class="table-wrap" style="margin-top:8px;max-height:400px;overflow:auto"><table><thead><tr><th></th><th>Delivery</th><th>Customer</th><th>Date</th><th>Estimated</th><th>Actual Billed Cost</th></tr></thead><tbody>
@forelse($unbilledDeliveries as $d)
<tr>
<td><input type="checkbox" name="delivery_ids[]" value="{{ $d->id }}"></td>
<td>{{ $d->delivery_note_number }}</td>
<td>{{ $d->customer->name }}</td>
<td>{{ $d->delivery_date?->format('d M Y') }}</td>
<td class="amount">AED {{ number_format($d->estimated_cost,2) }}</td>
<td><input type="number" step="0.01" name="actual_costs[{{ $d->id }}]" value="{{ $d->estimated_cost }}" style="width:100px"></td>
</tr>
@empty<tr><td colspan="6" class="empty">No unbilled courier deliveries.</td></tr>@endforelse
</tbody></table></div>

<div class="actions" style="margin-top:15px"><button class="btn primary">Create Bill</button></div>
</form>
</div>
@endsection
