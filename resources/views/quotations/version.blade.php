@extends('layouts.app')
@section('title',$quotation->quotation_number.' — Version '.$versionNumber)
@section('subtitle','Read-only historical snapshot')
@section('content')
<div class="alert" style="border-color:var(--brown);background:rgba(34,211,238,.08)">
Viewing <b>Version {{ $versionNumber }}</b> of {{ $quotation->quotation_number }} — a read-only historical snapshot, not the live document.
@if($isLatest)<span class="badge green" style="margin-left:8px">This is the current version</span>@else<span class="badge amber" style="margin-left:8px">An older version — <a href="{{ route('quotations.show',$quotation) }}">view current version</a></span>@endif
</div>

<div class="grid cols-3">
<div class="card"><h2>Customer</h2><p>{{ $quotation->customer->name }}</p></div>
<div class="card"><h2>Saved</h2><p>{{ $createdAt->format('d M Y, h:i A') }} by {{ $createdBy?->name??'System' }}</p></div>
<div class="card"><h2>Totals (this version)</h2><p>Subtotal AED {{ number_format($snapshot['subtotal']??0,2) }}<br>Discount AED {{ number_format($snapshot['discount_total']??0,2) }}<br>VAT AED {{ number_format($snapshot['tax_total']??0,2) }}<br><b>Total AED {{ number_format($snapshot['grand_total']??0,2) }}</b></p></div>
</div>

<div class="card" style="margin-top:18px"><h2>Items (this version)</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>VAT</th><th>Total</th></tr></thead><tbody>
@forelse(($snapshot['items']??[]) as $item)
<tr><td>{{ $item['description']??'—' }}</td><td>{{ $item['qty']??0 }}</td><td>AED {{ number_format($item['unit_price']??0,2) }}</td><td>AED {{ number_format($item['tax_amount']??0,2) }}</td><td class="amount">AED {{ number_format($item['line_total']??0,2) }}</td></tr>
@empty
<tr><td colspan="5" class="empty">No items recorded in this version.</td></tr>
@endforelse
</tbody></table></div></div>

@if(!empty($snapshot['notes']))
<div class="card" style="margin-top:18px"><h2>Notes (this version)</h2><p>{{ $snapshot['notes'] }}</p></div>
@endif

<div class="actions" style="margin-top:18px"><a class="btn" href="{{ route('quotations.show',$quotation) }}">Back to current version</a></div>
@endsection
