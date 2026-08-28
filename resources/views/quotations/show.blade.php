@extends('layouts.app') @section('title',$quotation->quotation_number) @section('subtitle','Quotation for '.$quotation->customer->name)
@php($settings=\App\Models\Setting::pluck('value','key'))
@section('content')<div class="toolbar no-print"><div><button class="btn" onclick="print()">Print / PDF</button>@if($quotation->status!=='converted'&&auth()->user()->hasPermission('quotations.manage'))<a class="btn" href="{{ route('quotations.edit',$quotation) }}">Edit</a>@endif</div><div style="display:flex;gap:8px">@if(auth()->user()->hasPermission('quotations.manage')&&$quotation->status!=='converted')<form method="post" action="{{ route('quotations.status',$quotation) }}">@csrf @method('patch')<select name="status" onchange="this.form.submit()">@foreach(['draft','sent','viewed','approved','rejected','expired'] as $s)<option @selected($quotation->status===$s)>{{ $s }}</option>@endforeach</select></form>@endif @if($quotation->status==='approved'&&auth()->user()->hasPermission('orders.manage'))<button type="button" class="btn primary" onclick="document.getElementById('convert-to-order').showModal()">Convert to Sales Order</button>@endif</div></div>
@if($quotation->status==='converted')<div class="alert" style="margin-bottom:12px">This quotation has already been converted to a Sales Order.</div>@endif
@error('convert')<div class="alert danger">{{ $message }}</div>@enderror
@error('manual_reference')<div class="alert danger">{{ $message }}</div>@enderror
<div class="card document-a4"><h1 class="doc-type-heading">Quotation</h1><div class="doc-header"><div>@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="doc-logo">@endif<p class="muted">TRN {{ $settings['company_trn'] ?? '—' }}</p>@if($settings['company_address']??null)<p class="muted">{{ $settings['company_address'] }}</p>@endif@if($settings['company_phone']??null)<p class="muted">{{ $settings['company_phone'] }}</p>@endif</div><div><b>{{ $quotation->customer->name }}</b>@include('partials._customer-address-block',['customer'=>$quotation->customer])</div><div><p><b>Quotation Number: {{ $quotation->quotation_number }}</b></p><b>Date</b><p>{{ $quotation->quotation_date }}<br>Valid until {{ $quotation->valid_until?:'—' }}</p><span class="badge">{{ $quotation->status }}</span></div></div><div class="table-wrap" style="margin-top:20px"><table><thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Discount</th><th>VAT</th><th>Total</th></tr></thead><tbody>@foreach($quotation->items as $i)<tr><td>{{ $i->description }}</td><td>{{ $i->qty }}</td><td>AED {{ number_format($i->unit_price,2) }}</td><td>AED {{ number_format($i->discount,2) }}</td><td>AED {{ number_format($i->tax_amount,2) }}</td><td class="amount">AED {{ number_format($i->line_total,2) }}</td></tr>@endforeach</tbody></table></div><div class="summary" style="margin:20px 0 0 auto;max-width:480px"><div><small>Subtotal</small><strong>AED {{ number_format($quotation->subtotal,2) }}</strong></div><div><small>VAT</small><strong>AED {{ number_format($quotation->tax_total,2) }}</strong></div><div><small>Total</small><strong>AED {{ number_format($quotation->grand_total,2) }}</strong></div></div>@if($quotation->notes)<p><b>Notes</b><br>{!! nl2br(e($quotation->notes)) !!}</p>@endif@if($settings['quotation_terms']??null)<p><b>Terms & conditions</b><br>{!! nl2br(e($settings['quotation_terms'])) !!}</p>@endif<div class="doc-footer">@if($settings['document_footer']??null)<p class="muted doc-footer-text">{{ $settings['document_footer'] }}</p>@endif</div></div>

@if($quotation->status==='approved'&&auth()->user()->hasPermission('orders.manage'))
<dialog id="convert-to-order" class="quick-dialog">
<form method="post" action="{{ route('quotations.convert',$quotation) }}" data-confirm="Create a sales order and production job from this approved quotation? This cannot be undone.">
@csrf
<div class="dialog-head"><h2>Convert to Sales Order</h2><button type="button" class="dialog-close" onclick="document.getElementById('convert-to-order').close()">×</button></div>
<div class="form-grid">
<label>Order Number<input name="manual_reference" data-convert-manual-reference maxlength="10" required></label>
<label>Order Date<input type="date" name="order_date" data-convert-order-date value="{{ today()->toDateString() }}" required></label>
<label>Delivery Date<input type="date" name="delivery_date" value="{{ today()->addDays(3)->toDateString() }}" required></label>
<label>Priority<select name="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="high">High</option></select></label>
<label>Final order number<output data-convert-final-number>—</output></label>
</div>
<p class="muted" style="padding:0 22px 10px;font-size:12.5px">All {{ $quotation->items->count() }} item(s) from this quotation — including any manual (non-product) lines — will carry over to the new Sales Order exactly as they appear here.</p>
<div class="actions"><button type="button" class="btn" onclick="document.getElementById('convert-to-order').close()">Cancel</button><button type="submit" class="btn primary">Create Sales Order</button></div>
</form>
</dialog>
@endif
@endsection
@push('scripts')<script src="{{ asset('build/assets/order-entry.js') }}?v={{ @filemtime(public_path('build/assets/order-entry.js')) ?: time() }}" defer></script>@endpush
