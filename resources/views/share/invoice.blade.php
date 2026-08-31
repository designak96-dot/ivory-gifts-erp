<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $invoice->invoice_number }}</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="{{ asset('build/assets/app.css') }}">
<style>
.no-print{display:none!important}
body{padding:24px}
@media print{body{padding:0}}
#fit-wrapper{transform-origin:top left}
.doc-header{flex-wrap:wrap;gap:16px}
@media(max-width:640px){.doc-header{flex-direction:column}}
</style>
@if(request()->boolean('download'))<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),300));</script>@endif
</head>
<body>
<div id="fit-wrapper">
@php($settings=\App\Models\Setting::pluck('value','key'))
<div class="card document-a4"><h1 class="doc-type-heading">Tax Invoice</h1><div class="doc-header"><div>@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="doc-logo">@endif<p class="muted">TRN {{ $settings['company_trn'] ?? '—' }}</p>@if($settings['company_address']??null)<p class="muted">{{ $settings['company_address'] }}</p>@endif@if($settings['company_phone']??null)<p class="muted">{{ $settings['company_phone'] }}</p>@endif</div><div><b>Bill to</b><p><b>{{ $invoice->customer->name }}</b></p>@include('partials._customer-address-block',['customer'=>$invoice->customer])</div><div><p><b>Invoice Number: {{ $invoice->invoice_number }}</b></p><b>Invoice date</b><p>{{ $invoice->invoice_date->format('d M Y') }}<br>Due {{ $invoice->due_date?->format('d M Y')??'—' }}</p><span class="badge {{ $invoice->status==='paid'?'green':'amber' }}">{{ str_replace('_',' ',$invoice->status) }}</span></div></div><div class="table-wrap" style="margin-top:20px"><table><thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>VAT</th><th>Total</th></tr></thead><tbody>@foreach($invoice->items as $i)<tr><td>{{ $i->description }}</td><td>{{ $i->qty }}</td><td>AED {{ number_format($i->rate,2) }}</td><td>AED {{ number_format($i->tax_amount,2) }}</td><td class="amount">AED {{ number_format($i->line_total,2) }}</td></tr>@endforeach</tbody></table></div>@if($settings['invoice_terms']??null)<p><b>Terms & conditions</b><br>{!! nl2br(e($settings['invoice_terms'])) !!}</p>@endif@if($settings['company_bank_details']??null)<p><b>Payment details</b><br>{!! nl2br(e($settings['company_bank_details'])) !!}</p>@endif<div class="doc-keep-together"><div class="summary" style="margin:20px 0 0 auto;max-width:480px"><div><small>Total</small><strong>AED {{ number_format($invoice->grand_total,2) }}</strong></div><div><small>Paid</small><strong class="kpi-good">AED {{ number_format($invoice->amount_paid,2) }}</strong></div><div><small>Balance due</small><strong class="kpi-bad">AED {{ number_format($invoice->outstanding_amount,2) }}</strong></div></div><div class="doc-footer">@if($settings['signature_path']??null)<div class="doc-signature-block"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['signature_path']) }}" alt="Authorized signature" class="doc-signature"></div>@endif@if($settings['document_footer']??null)<p class="muted doc-footer-text">{{ $settings['document_footer'] }}</p>@endif</div></div></div>
</div>
@unless(request()->boolean('download'))
<script>
(function(){
  // Fit-to-width using CSS `zoom` rather than `transform:scale()`.
  // zoom genuinely affects the element's rendered layout box — unlike
  // transform, which is a purely visual, post-layout effect — so nested
  // overflow:auto containers (like the items table) correctly see a
  // smaller effective width and stop needing their own internal scroll.
  // This runs synchronously as soon as the DOM is ready, not on a
  // delayed timer, and only ever shrinks (never enlarges) the natural
  // size. The browser's native pinch-zoom / Ctrl+scroll zoom still
  // works normally on top of this afterward.
  function fitToWidth(){
    var wrapper = document.getElementById('fit-wrapper');
    if (!wrapper) return;
    wrapper.style.zoom = 1;
    // A nested overflow:auto container (the items table) clips its true
    // content width from a simple wrapper.scrollWidth check, so measure
    // the table element itself directly too.
    var naturalWidth = wrapper.scrollWidth;
    wrapper.querySelectorAll('table').forEach(function(t){ naturalWidth = Math.max(naturalWidth, t.scrollWidth); });
    var bodyStyle = getComputedStyle(document.body);
    var bodyPadding = parseFloat(bodyStyle.paddingLeft) + parseFloat(bodyStyle.paddingRight);
    var available = document.documentElement.clientWidth - bodyPadding - 4;
    var ratio = Math.min(1, (available / naturalWidth) * 0.97);
    wrapper.style.zoom = ratio;
  }
  document.addEventListener('DOMContentLoaded', fitToWidth);
  window.addEventListener('resize', fitToWidth);
  if (document.readyState !== 'loading') fitToWidth();
})();
</script>
@endunless
</body>
</html>
