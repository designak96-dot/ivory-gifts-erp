@extends('layouts.app')
@section('title','Purchases & Suppliers')
@section('subtitle','Suppliers, Raw Materials, direct purchase entry, and price comparison')
@section('content')

<div class="card">
<div class="toolbar"><h2>Suppliers</h2><a class="btn" href="{{ route('suppliers.index') }}">Manage Suppliers</a></div>
</div>

@if(auth()->user()->hasPermission('purchases.manage'))
<div class="card" style="margin-top:18px">
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
<div class="toolbar"><h2>Raw Materials</h2><form><input name="q" value="{{ request('q') }}" placeholder="Search name or code"><button class="btn">Search</button></form></div>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Current Stock</th><th>Reorder Level</th><th>Latest Cost</th><th>Preferred Supplier</th><th></th></tr></thead><tbody>
@forelse($materials as $m)
<tr @class(['kpi-bad-row'=>$m->current_stock<=$m->reorder_level])>
<td><b>{{ $m->code }}</b></td>
<td>{{ $m->name }}</td>
<td>{{ $m->category?:'—' }}</td>
<td class="amount">{{ number_format($m->current_stock,3) }} {{ $m->unit }} @if($m->current_stock<=$m->reorder_level)<span class="badge red" style="margin-left:6px">Low</span>@endif</td>
<td class="amount">{{ number_format($m->reorder_level,3) }}</td>
<td class="amount">{{ $m->latest_cost>0?'AED '.number_format($m->latest_cost,4):'—' }}</td>
<td>{{ $m->preferredSupplier?->name?:'—' }}</td>
<td><a href="{{ route('raw-materials.show',$m) }}" class="btn small">Price History</a></td>
</tr>
@empty
<tr><td colspan="8" class="empty">No raw materials added yet.</td></tr>
@endforelse
</tbody></table></div>{{ $materials->links() }}
</div>

@if(auth()->user()->hasPermission('purchases.manage'))
<div class="card" style="margin-top:18px"><h2>Record Purchase</h2>
<p class="muted" style="margin-top:6px">One supplier invoice can cover multiple materials — add as many lines as the invoice needs.</p>
<form method="post" action="{{ route('raw-material-purchases.store') }}" enctype="multipart/form-data" style="margin-top:15px" id="rmp-form">
@csrf
<div class="form-grid">
<label>Supplier<select name="supplier_id" required><option value="">Select...</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
<label>Purchase Date<input type="date" name="purchase_date" value="{{ today()->toDateString() }}" required></label>
<label>Payment Method<select name="payment_method" id="rmp-method" required><option value="cash">Cash</option><option value="bank">Bank</option><option value="unpaid">Unpaid (Supplier Payable)</option></select></label>
<label id="rmp-bank-account-label" style="display:none">Bank Account<select name="bank_account_id"><option value="">Select...</option>@foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach</select></label>
<label>Payment Reference <span class="muted">(strongest reconciliation match)</span><input name="payment_reference"></label>
<label class="span-2">Notes<input name="notes"></label>
<label class="span-2">Supplier Invoice/Bill<input type="file" name="invoice" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
</div>

<h3 style="margin-top:18px">Line Items</h3>
<div id="rmp-lines"></div>
<button type="button" class="btn small" id="rmp-add-line" style="margin-top:8px">+ Add Line</button>

<p style="margin-top:15px"><b>Grand Total: <span id="rmp-grand-total">AED 0.00</span></b></p>
<div class="actions"><button class="btn primary">Record Purchase</button></div>
</form>
</div>
@endif

<div class="card" style="margin-top:18px"><h2>Purchase History</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Purchase</th><th>Date</th><th>Supplier</th><th>Materials</th><th>Total</th><th>Payment</th><th>Invoice</th></tr></thead><tbody>
@forelse($recentPurchases as $p)
<tr>
<td><b>{{ $p->purchase_number }}</b></td>
<td>{{ $p->purchase_date->format('d M Y') }}</td>
<td>{{ $p->supplier->name }}</td>
<td>{{ $p->lines->pluck('rawMaterial.name')->filter()->implode(', ') }}</td>
<td class="amount"><b>AED {{ number_format($p->total_amount,2) }}</b></td>
<td><span class="badge {{ $p->payment_method==='unpaid'?'amber':'green' }}">{{ ucfirst($p->payment_method) }}</span>@if($p->payment_reference)<div class="muted" style="font-size:11px">{{ $p->payment_reference }}</div>@endif</td>
<td>@if($p->invoice_path)<a class="btn small" href="{{ route('raw-material-purchases.invoice',$p) }}" target="_blank">View</a>@else<span class="muted">—</span>@endif</td>
</tr>
@empty
<tr><td colspan="7" class="empty">No purchases recorded yet.</td></tr>
@endforelse
</tbody></table></div></div>

@push('scripts')
<script>
(function(){
  var method = document.getElementById('rmp-method');
  var bankLabel = document.getElementById('rmp-bank-account-label');
  function toggleBank(){ bankLabel.style.display = method.value === 'bank' ? '' : 'none'; }
  method?.addEventListener('change', toggleBank);
  toggleBank();

  var materialOptions = `<option value="">Material...</option>@foreach($materials as $m)<option value="{{ $m->id }}" data-unit="{{ $m->unit }}">{{ $m->name }} ({{ $m->code }})</option>@endforeach`;
  var linesContainer = document.getElementById('rmp-lines');
  var lineIndex = 0;
  var grandTotalEl = document.getElementById('rmp-grand-total');

  function recalcGrandTotal(){
    var total = 0;
    linesContainer.querySelectorAll('.rmp-line').forEach(function(row){
      var qty = parseFloat(row.querySelector('.rmp-qty').value) || 0;
      var price = parseFloat(row.querySelector('.rmp-price').value) || 0;
      var tax = parseFloat(row.querySelector('.rmp-tax').value) || 0;
      var lineTotal = qty * price + tax;
      row.querySelector('.rmp-line-total').textContent = lineTotal.toFixed(2);
      total += lineTotal;
    });
    grandTotalEl.textContent = 'AED ' + total.toFixed(2);
  }

  function addLine(){
    var i = lineIndex++;
    var row = document.createElement('div');
    row.className = 'rmp-line';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;gap:8px;align-items:end;margin-bottom:8px';
    row.innerHTML =
      '<label>Material<select name="lines['+i+'][raw_material_id]" class="rmp-material" required>'+materialOptions+'</select></label>' +
      '<label>Quantity<input type="number" step="0.001" min="0.001" name="lines['+i+'][quantity]" class="rmp-qty" required></label>' +
      '<label>Unit<input name="lines['+i+'][unit]" class="rmp-unit" required></label>' +
      '<label>Unit Price<input type="number" step="0.0001" min="0.0001" name="lines['+i+'][unit_price]" class="rmp-price" required></label>' +
      '<label>VAT<input type="number" step="0.01" min="0" name="lines['+i+'][tax_amount]" class="rmp-tax" value="0"></label>' +
      '<label>Line Total<input type="text" class="rmp-line-total" disabled value="0.00"></label>' +
      '<button type="button" class="btn small rmp-remove-line">Remove</button>';
    linesContainer.appendChild(row);

    row.querySelector('.rmp-material').addEventListener('change', function(){
      var unit = this.selectedOptions[0]?.dataset.unit || '';
      row.querySelector('.rmp-unit').value = unit;
    });
    row.querySelectorAll('.rmp-qty, .rmp-price, .rmp-tax').forEach(function(el){ el.addEventListener('input', recalcGrandTotal); });
    row.querySelector('.rmp-remove-line').addEventListener('click', function(){ row.remove(); recalcGrandTotal(); });
  }

  document.getElementById('rmp-add-line')?.addEventListener('click', addLine);
  addLine(); // start with one line
})();
</script>
@endpush
@endsection
