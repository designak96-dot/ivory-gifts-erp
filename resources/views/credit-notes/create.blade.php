@extends('layouts.app')
@section('title','New Credit Note')
@section('subtitle',$invoice ? 'Against invoice '.$invoice->invoice_number : 'Issue a refund or credit')
@section('content')
<form method="post" action="{{ route('credit-notes.store') }}" class="card">@csrf
<div class="form-grid">
<label>Customer<select name="customer_id" required>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id',$invoice?->customer_id)==$c->id)>{{ $c->name }}</option>@endforeach</select></label>
<label>Invoice (optional)<input type="hidden" name="invoice_id" value="{{ old('invoice_id',$invoice?->id) }}"><input value="{{ $invoice?->invoice_number??'None selected' }}" disabled></label>
<label>Sales Order (optional)<input type="number" name="sales_order_id" value="{{ old('sales_order_id',$invoice?->sales_order_id) }}" placeholder="Order ID"></label>
<label>Credit date<input type="date" name="credit_date" value="{{ old('credit_date',now()->toDateString()) }}" required></label>
<label class="span-2">Reason<input name="reason" required placeholder="e.g. Damaged item returned, pricing correction"></label>
</div>
<h2 style="margin-top:20px">Items to credit</h2>
<table style="width:100%;margin-top:10px" id="cn-items"><thead><tr><th>Description</th><th>Product</th><th>Qty</th><th>Unit price</th></tr></thead><tbody>
@if($invoice)
@foreach($invoice->items as $i)
<tr><td><input name="items[{{ $loop->index }}][description]" value="{{ $i->description }}" required></td><td><select name="items[{{ $loop->index }}][product_id]"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected($p->id===$i->product_id)>{{ $p->name_en }}</option>@endforeach</select></td><td><input type="number" step="0.001" name="items[{{ $loop->index }}][qty]" value="{{ $i->qty }}" required></td><td><input type="number" step="0.01" name="items[{{ $loop->index }}][unit_price]" value="{{ $i->rate }}" required></td></tr>
@endforeach
@else
<tr><td><input name="items[0][description]" required></td><td><select name="items[0][product_id]"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name_en }}</option>@endforeach</select></td><td><input type="number" step="0.001" name="items[0][qty]" value="1" required></td><td><input type="number" step="0.01" name="items[0][unit_price]" required></td></tr>
@endif
</tbody></table>
<div class="actions"><a class="btn" href="{{ route('credit-notes.index') }}">Cancel</a><button class="btn primary" data-confirm="Post this credit note? This will reduce the linked invoice's outstanding balance and cannot be undone.">Post credit note</button></div>
</form>
@endsection
