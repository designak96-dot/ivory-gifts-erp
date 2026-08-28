@extends('layouts.app')
@section('title',$delivery->delivery_note_number)
@section('subtitle',$delivery->customer->name.' · '.$delivery->salesOrder->order_number)
@section('content')
@php
    $address=$delivery->salesOrder->delivery_address ?: $delivery->customer->delivery_address;
    $mapUrl=$delivery->location_url ?: ($address?'https://www.google.com/maps/search/?api=1&query='.urlencode($address):null);
    $message='Hello '.$delivery->customer->name.', your Ivory Gifts order '.$delivery->salesOrder->order_number.' is scheduled for '.$delivery->delivery_date?->format('d M Y').'.';
    $settings=\App\Models\Setting::pluck('value','key');
    $hidePrices=request()->boolean('hide_prices',($settings['delivery_note_hide_prices']??'0')==='1');
@endphp
<div class="toolbar no-print"><div><a class="btn" href="{{ route('deliveries.index') }}">Back to schedule</a><button class="btn" onclick="print()">Print delivery note</button><a class="btn" href="{{ request()->fullUrlWithQuery(['hide_prices'=>$hidePrices?0:1]) }}">{{ $hidePrices ? 'Show prices' : 'Hide prices' }}</a></div><div>@if($mapUrl)<a class="btn" target="_blank" href="{{ $mapUrl }}">Open map</a>@endif @if($delivery->customer->whatsapp_url)<a class="btn success" target="_blank" href="{{ $delivery->customer->whatsapp_url }}?text={{ urlencode($message) }}">WhatsApp customer</a>@endif</div></div>
<div class="card delivery-document document-a4">
    <h1 class="doc-type-heading">Delivery Note</h1><div class="doc-header"><div>@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="doc-logo">@endif<p class="muted">TRN {{ $settings['company_trn'] ?? '—' }}</p>@if($settings['company_address']??null)<p class="muted">{{ $settings['company_address'] }}</p>@endif@if($settings['company_phone']??null)<p class="muted">{{ $settings['company_phone'] }}</p>@endif</div><div><b>Deliver to</b><p><b>{{ $delivery->customer->name }}</b></p>@include('partials._customer-address-block',['customer'=>$delivery->customer,'deliveryAddressOverride'=>$address])</div><div><p><b>{{ $delivery->delivery_note_number }}</b></p><p class="muted">Order Number: {{ $delivery->salesOrder->order_number }}</p><b>Delivery date</b><p>{{ $delivery->delivery_date?->format('d M Y') ?? 'Not set' }}</p><span class="badge {{ $delivery->status==='delivered'?'green':'amber' }}">{{ str($delivery->status)->replace('_',' ')->title() }}</span></div></div>
    <div class="delivery-meta"><span><small>Driver</small><b>{{ $delivery->driver?->name ?? 'Unassigned' }}</b></span><span><small>Package</small><b>{{ ucfirst($delivery->package_size) }}</b></span>@unless($hidePrices)<span><small>Delivery charge</small><b>AED {{ number_format($delivery->delivery_charge,2) }}</b></span>@endunless<span><small>Attempts</small><b>{{ $delivery->attempt_count }}</b></span></div>
    <div class="table-wrap"><table><thead><tr><th>Item</th><th>Customisation</th><th>Quantity</th>@unless($hidePrices)<th>Rate</th><th>Total</th>@endunless</tr></thead><tbody>@foreach($delivery->salesOrder->items as $item)<tr><td>{{ $item->description }}</td><td>{{ $item->customisation['notes']??'—' }}</td><td>{{ $item->qty }}</td>@unless($hidePrices)<td>AED {{ number_format($item->unit_price,2) }}</td><td class="amount">AED {{ number_format($item->line_total,2) }}</td>@endunless</tr>@endforeach</tbody></table></div>
    <div class="doc-footer">@if($settings['document_footer']??null)<p class="muted doc-footer-text">{{ $settings['document_footer'] }}</p>@endif</div>
</div>
@if(auth()->user()->hasPermission('deliveries.manage'))
<form method="post" enctype="multipart/form-data" action="{{ route('deliveries.update',$delivery) }}" class="card no-print delivery-update-form">@csrf @method('patch')
    <div class="card-header"><div><h2>{{ $isDriverOnly ? 'Driver update' : 'Schedule and delivery update' }}</h2><p class="muted">Changes appear automatically for other logged-in staff.</p></div></div>
    <div class="form-grid">
        <label>Status<select name="status">@foreach(['pending','out_for_delivery','delivered','partial','failed','returned'] as $status)<option value="{{ $status }}" @selected(old('status',$delivery->status)===$status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        @unless($isDriverOnly)
            <label>Driver<select name="driver_id"><option value="">Unassigned</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected((string)old('driver_id',$delivery->driver_id)===(string)$driver->id)>{{ $driver->name }}</option>@endforeach</select></label>
            <label>Delivery date<div class="input-action"><input type="date" name="delivery_date" value="{{ old('delivery_date',$delivery->delivery_date?->toDateString()) }}" data-delivery-date><button type="button" class="btn small" data-next-date data-url="{{ route('deliveries.next',['delivery_id'=>$delivery->id]) }}">Next available</button></div><small data-next-date-label>Suggested: {{ $suggestedDate->format('D, d M Y') }}</small></label>
            <label>Package size<select name="package_size"><option value="standard" @selected(old('package_size',$delivery->package_size)==='standard')>Standard — normal items</option><option value="large" @selected(old('package_size',$delivery->package_size)==='large')>Large stand</option><option value="pickup" @selected(old('package_size',$delivery->package_size)==='pickup')>Customer pickup</option></select></label>
            <label>Delivery charge (AED)<input type="number" step="0.01" min="0" name="delivery_charge" value="{{ old('delivery_charge',$delivery->delivery_charge) }}"><small>Enter the approved delivery charge.</small></label>
        @else<input type="hidden" name="package_size" value="{{ $delivery->package_size }}">@endunless
        <label>Google Maps link<input type="url" name="location_url" value="{{ old('location_url',$delivery->location_url) }}" placeholder="https://maps.google.com/..."></label>
        <label>Recipient name<input name="recipient_name" value="{{ old('recipient_name',$delivery->recipient_name) }}"></label>
        <label>Proof-of-delivery photo<input type="file" name="pod_photo" accept="image/*" capture="environment"><small>Required when marking Delivered.</small></label>
        <label>Signature image<input type="file" name="signature" accept="image/*"></label>
        <label>Failure reason<textarea name="failure_reason">{{ old('failure_reason',$delivery->failure_reason) }}</textarea></label>
        <label>Delivery notes<textarea name="delivery_notes">{{ old('delivery_notes',$delivery->delivery_notes) }}</textarea></label>
    </div><div class="actions"><button class="btn primary">Save delivery update</button></div>
</form>
@endif
@if($delivery->pod_photo_path || $delivery->signature_path)<div class="grid cols-2 delivery-proof">@if($delivery->pod_photo_path)<div class="card"><h2>Proof of delivery</h2><img src="{{ asset('storage/'.$delivery->pod_photo_path) }}" alt="Proof of delivery"></div>@endif @if($delivery->signature_path)<div class="card"><h2>Recipient signature</h2><img src="{{ asset('storage/'.$delivery->signature_path) }}" alt="Recipient signature"></div>@endif</div>@endif
@endsection
@push('scripts')<script src="{{ asset('build/assets/delivery.js') }}?v={{ @filemtime(public_path('build/assets/delivery.js')) ?: time() }}" defer></script>@endpush
