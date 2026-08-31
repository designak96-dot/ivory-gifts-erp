@extends('layouts.app')
@section('title','Expenses')
@section('subtitle','Operating expenses post directly to the general ledger')
@section('content')
@if(auth()->user()->hasPermission('expenses.manage'))
<form method="post" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="card">
@csrf
<h2>Post expense</h2>
<div class="form-grid" style="margin-top:15px">
<label>Date<input type="date" name="expense_date" value="{{ today()->toDateString() }}" required></label>
<label>Category<input name="category" placeholder="Rent, transport, materials..." required></label>
<label>Payee<input name="payee"></label>
<label>Payment method<select name="payment_method"><option>cash</option><option>bank</option><option>card</option></select></label>
<label>Amount before VAT<input type="number" step=".01" min=".01" name="amount_ex_tax" required></label>
<label>VAT amount<input type="number" step=".01" min="0" name="tax_amount" value="0"></label>
<label>Reference<input name="reference"></label>
<label>Description<input name="description"></label>

<div class="span-2 upload-dropzone" data-dropzone data-target="invoice-file-input">
<label style="display:block;margin-bottom:6px">Expense Invoice / Bill <span class="muted">(optional)</span></label>
<div class="dropzone-inner" data-dropzone-inner>
<p class="muted" data-dropzone-label>Drag & drop a file here, or click to browse</p>
<input type="file" name="invoice" id="invoice-file-input" accept=".jpg,.jpeg,.png,.webp,.pdf" data-dropzone-input hidden>
</div>
</div>

<div class="span-2 upload-dropzone" data-dropzone data-target="proof-file-input">
<label style="display:block;margin-bottom:6px">Payment Proof / Slip <span class="muted">(required)</span></label>
<div class="dropzone-inner" data-dropzone-inner>
<p class="muted" data-dropzone-label>Drag & drop a file here, or click to browse</p>
<input type="file" name="proof" id="proof-file-input" accept=".jpg,.jpeg,.png,.webp,.pdf" data-dropzone-input required hidden>
</div>
</div>

</div>
<div class="actions"><button class="btn primary">Post expense</button></div>
</form>
@endif

<div class="card" style="margin-top:18px"><h2>Expense register</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Expense</th><th>Date</th><th>Category</th><th>Payee</th><th>Method</th><th>Total</th><th>Invoice/Bill</th><th>Payment Proof</th></tr></thead><tbody>
@forelse($expenses as $e)
<tr>
<td><b>{{ $e->expense_number }}</b></td>
<td>{{ $e->expense_date->format('d M Y') }}</td>
<td>{{ $e->category }}</td>
<td>{{ $e->payee?:'—' }}</td>
<td>{{ $e->payment_method }}</td>
<td class="amount">AED {{ number_format($e->total_amount,2) }}</td>
<td data-label="Invoice/Bill">@if($e->invoice_path)<button type="button" class="btn small" data-view-proof="{{ route('expenses.invoice',$e) }}">View</button> <a class="btn small" href="{{ route('expenses.invoice',$e) }}?download=1">Download</a>@else<span class="muted">Not provided</span>@endif</td>
<td data-label="Payment Proof">@if($e->proof_path)<button type="button" class="btn small" data-view-proof="{{ route('expenses.proof',$e) }}">View</button> <a class="btn small" href="{{ route('expenses.proof',$e) }}?download=1">Download</a>@else<span class="muted">— (historical)</span>@endif</td>
</tr>
@empty
<tr><td colspan="8" class="empty">No expenses posted.</td></tr>
@endforelse
</tbody></table></div>{{ $expenses->links() }}</div>

@push('scripts')
<script>
document.querySelectorAll('[data-dropzone]').forEach(function(zone){
  var input = zone.querySelector('[data-dropzone-input]');
  var inner = zone.querySelector('[data-dropzone-inner]');
  var label = zone.querySelector('[data-dropzone-label]');

  function showFileName(){
    if (input.files && input.files.length) {
      label.textContent = input.files[0].name;
      inner.classList.add('has-file');
    } else {
      label.textContent = 'Drag & drop a file here, or click to browse';
      inner.classList.remove('has-file');
    }
  }

  inner.addEventListener('click', function(){ input.click(); });
  input.addEventListener('change', showFileName);

  ['dragenter','dragover'].forEach(function(evt){
    inner.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); inner.classList.add('dragover'); });
  });
  ['dragleave','drop'].forEach(function(evt){
    inner.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); inner.classList.remove('dragover'); });
  });
  inner.addEventListener('drop', function(e){
    var files = e.dataTransfer.files;
    if (files && files.length) {
      input.files = files;
      showFileName();
    }
  });
});
</script>
@endpush
@endsection
