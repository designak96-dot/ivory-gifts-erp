@extends('layouts.app')
@section('title',$material->name)
@section('subtitle',$material->code.' · '.($material->category?:'Raw material'))
@section('content')

<div class="grid cols-4">
<div class="stat"><small>Current Stock</small><strong>{{ number_format($material->current_stock,3) }} {{ $material->unit }}</strong></div>
<div class="stat"><small>Reorder Level</small><strong>{{ number_format($material->reorder_level,3) }} {{ $material->unit }}</strong></div>
<div class="stat"><small>Latest Cost</small><strong>{{ $priceHistory['latest']!==null?'AED '.number_format($priceHistory['latest'],4):'—' }}</strong></div>
<div class="stat"><small>Preferred Supplier</small><strong>{{ $material->preferredSupplier?->name?:'—' }}</strong></div>
</div>

<div class="card" style="margin-top:18px"><h2>Price History</h2>
@if($priceHistory['latest']===null)
<p class="muted" style="margin-top:12px">No purchases recorded yet.</p>
@else
<div class="grid cols-4" style="margin-top:15px">
<div class="stat"><small>Previous Price</small><strong>{{ $priceHistory['previous']!==null?'AED '.number_format($priceHistory['previous'],4):'—' }}</strong></div>
<div class="stat"><small>Latest Price</small><strong>AED {{ number_format($priceHistory['latest'],4) }}</strong></div>
<div class="stat"><small>Lowest Price</small><strong class="kpi-good">AED {{ number_format($priceHistory['lowest'],4) }}</strong></div>
<div class="stat"><small>Highest Price</small><strong class="kpi-bad">AED {{ number_format($priceHistory['highest'],4) }}</strong></div>
</div>
@if($priceHistory['change_percent']!==null)
<p style="margin-top:12px">
<b>Change vs previous purchase:</b>
<span class="badge {{ $priceHistory['change_percent']>0?'red':($priceHistory['change_percent']<0?'green':'') }}">
{{ $priceHistory['change_percent']>0?'▲ +':($priceHistory['change_percent']<0?'▼ ':'') }}{{ number_format($priceHistory['change_percent'],1) }}%
</span>
</p>
@endif

@if(count($priceHistory['by_supplier'])>1)
<h3 style="margin-top:18px">Compare Supplier Prices</h3>
<div class="table-wrap" style="margin-top:10px"><table><thead><tr><th>Supplier</th><th>Latest Price</th><th>Purchases</th></tr></thead><tbody>
@foreach($priceHistory['by_supplier'] as $row)
<tr><td>{{ $row['supplier']->name }}</td><td class="amount">AED {{ number_format($row['latest_price'],4) }}</td><td>{{ $row['purchase_count'] }}</td></tr>
@endforeach
</tbody></table></div>
@endif
@endif
</div>

@if(auth()->user()->hasPermission('purchases.manage'))
<div class="card" style="margin-top:18px"><h2>Record Purchase</h2>
<form method="post" action="{{ route('raw-materials.purchases.store',$material) }}" enctype="multipart/form-data" style="margin-top:15px" id="rm-purchase-form">
@csrf
<div class="form-grid">
<label>Supplier<select name="supplier_id" required><option value="">Select...</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($material->preferred_supplier_id===$s->id)>{{ $s->name }}</option>@endforeach</select></label>
<label>Purchase Date<input type="date" name="purchase_date" value="{{ today()->toDateString() }}" required></label>
<label>Quantity<input type="number" step="0.001" min="0.001" name="quantity" required id="rm-qty"></label>
<label>Unit<input name="unit" value="{{ $material->unit }}" required></label>
<label>Unit Price<input type="number" step="0.0001" min="0.0001" name="unit_price" required id="rm-price"></label>
<label>VAT Amount<input type="number" step="0.01" min="0" name="tax_amount" value="0" id="rm-tax"></label>
<label>Total <span class="muted">(calculated)</span><input type="text" id="rm-total-display" disabled placeholder="0.00"></label>
<label>Payment Method<select name="payment_method" id="rm-method" required><option value="cash">Cash</option><option value="bank">Bank</option><option value="unpaid">Unpaid (Supplier Payable)</option></select></label>
<label id="rm-bank-account-label" style="display:none">Bank Account<select name="bank_account_id"><option value="">Select...</option>@foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach</select></label>
<label>Payment Reference <span class="muted">(strongest reconciliation match)</span><input name="payment_reference"></label>
<label class="span-2">Notes<input name="notes"></label>
<label class="span-2">Supplier Invoice/Bill<input type="file" name="invoice" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
</div>
<div class="actions"><button class="btn primary">Record Purchase</button></div>
</form>
</div>
@endif

<div class="card" style="margin-top:18px"><h2>Purchase History</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Purchase</th><th>Date</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>VAT</th><th>Total</th><th>Payment</th><th>Invoice</th></tr></thead><tbody>
@forelse($purchases as $p)
<tr>
<td><b>{{ $p->purchase_number }}</b></td>
<td>{{ $p->purchase_date->format('d M Y') }}</td>
<td>{{ $p->supplier->name }}</td>
<td class="amount">{{ number_format($p->quantity,3) }} {{ $p->unit }}</td>
<td class="amount">AED {{ number_format($p->unit_price,4) }}</td>
<td class="amount">AED {{ number_format($p->tax_amount,2) }}</td>
<td class="amount"><b>AED {{ number_format($p->total_amount,2) }}</b></td>
<td><span class="badge {{ $p->payment_method==='unpaid'?'amber':'green' }}">{{ ucfirst($p->payment_method) }}</span>@if($p->payment_reference)<div class="muted" style="font-size:11px">{{ $p->payment_reference }}</div>@endif</td>
<td>@if($p->invoice_path)<a class="btn small" href="{{ route('raw-material-purchases.invoice',$p) }}" target="_blank">View</a>@else<span class="muted">—</span>@endif</td>
</tr>
@empty
<tr><td colspan="9" class="empty">No purchases recorded yet.</td></tr>
@endforelse
</tbody></table></div>{{ $purchases->links() }}</div>

@push('scripts')
<script>
(function(){
  var method = document.getElementById('rm-method');
  var bankLabel = document.getElementById('rm-bank-account-label');
  function toggleBank(){ bankLabel.style.display = method.value === 'bank' ? '' : 'none'; }
  method?.addEventListener('change', toggleBank);
  toggleBank();

  var qty = document.getElementById('rm-qty'), price = document.getElementById('rm-price'), tax = document.getElementById('rm-tax'), total = document.getElementById('rm-total-display');
  function recalc(){
    var q = parseFloat(qty.value) || 0, p = parseFloat(price.value) || 0, t = parseFloat(tax.value) || 0;
    total.value = (q * p + t).toFixed(2);
  }
  [qty, price, tax].forEach(function(el){ el?.addEventListener('input', recalc); });
})();
</script>
@endpush
@endsection
