{{-- Compact drag-and-drop Confirmed Order Proof widget. Shown only when the order is Confirmed. --}}
@if(($order->simple_confirmation ?? null) === 'confirmed')
@php($proof = $order->attachments()->where('category','Confirmed Order Proof')->latest()->first())
<span class="proof-widget" data-proof-widget data-order-id="{{ $order->id }}" data-upload-url="{{ route('orders.proof.store',$order) }}">
<input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" data-proof-input hidden>
@if($proof)
<span class="proof-chip proof-chip-has" data-proof-chip data-view-url="{{ route('order-attachments.download',$proof) }}" data-delete-url="{{ route('order-attachments.destroy',$proof) }}">
<a href="{{ route('order-attachments.download',$proof) }}" title="{{ $proof->original_name }}">✓ Proof</a>
<button type="button" data-proof-replace title="Replace">⟳</button>
@if(auth()->user()->hasPermission('orders.delete'))<button type="button" data-proof-delete title="Delete">×</button>@endif
</span>
@else
<button type="button" class="proof-chip proof-chip-empty" data-proof-trigger>+ Drop Proof</button>
@endif
</span>
@endif
