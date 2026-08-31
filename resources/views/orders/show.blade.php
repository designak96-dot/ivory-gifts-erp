@extends('layouts.app') @section('title',$order->order_number) @section('subtitle',$order->customer->name.' · Order workflow and commercial documents')
@section('content')<div class="toolbar"><div><span class="badge {{ $order->payment_status==='paid'?'green':'amber' }}">{{ $order->payment_status }}</span> @if($order->is_very_urgent)<span class="badge red">Very urgent</span>@endif @include('partials._confirmed-proof-widget',['order'=>$order]) @if(in_array($order->simple_status,['ready','delivered']))<button type="button" class="btn small success" data-whatsapp-share data-order-id="{{ $order->id }}" data-check-url="{{ route('whatsapp.check',$order) }}" data-link-url="{{ route('whatsapp.link',$order) }}" title="Share via WhatsApp">💬 WhatsApp</button>@endif</div><div style="display:flex;gap:8px;flex-wrap:wrap">@if(auth()->user()->hasPermission('orders.manage'))<a class="btn" href="{{ route('orders.repeat',$order) }}">Repeat order</a>@endif @php($activeInvoice=$order->invoices->first(fn($inv)=>$inv->status!=='cancelled'))@if(auth()->user()->hasPermission('invoices.manage'))@if($activeInvoice)<a class="btn" href="{{ route('invoices.show',$activeInvoice) }}">View invoice</a>@else<form method="post" action="{{ route('orders.invoice',$order) }}">@csrf<button class="btn" data-confirm="Generate and post the invoice?">Generate invoice</button></form>@endif@endif @if(auth()->user()->hasPermission('deliveries.manage'))<form method="post" action="{{ route('orders.delivery',$order) }}">@csrf<button class="btn primary">Create delivery note</button></form>@endif @if(auth()->user()->hasPermission('orders.delete'))<form method="post" action="{{ route('orders.destroy',$order) }}">@csrf @method('delete')<button type="submit" class="btn danger" data-confirm="Are you sure you want to delete this Sales Order?">Delete</button></form>@endif</div></div>@error('delete')<div class="alert danger">{{ $message }}</div>@enderror<div class="grid cols-3"><div class="card"><h2>Customer</h2><p><a href="{{ route('customers.show',$order->customer) }}"><b>{{ $order->customer->name }}</b></a><br>{{ $order->customer->phone }}<br>{{ $order->delivery_address }}</p></div><div class="card"><h2>Schedule</h2><p>Order: {{ $order->order_date->format('d M Y') }}<br>Delivery: {{ $order->delivery_date?->format('d M Y')??'Not set' }}<br>{{ $order->emirate }}</p></div><div class="card"><h2>Financial</h2><p>Subtotal AED {{ number_format($order->subtotal,2) }}<br>VAT AED {{ number_format($order->tax_total,2) }}<br><b>Total AED {{ number_format($order->grand_total,2) }}</b></p><p class="kpi-good">Paid AED {{ number_format($order->paid_amount,2) }}</p><p class="kpi-bad">Remaining AED {{ number_format($order->remaining_amount,2) }}</p><span class="badge {{ $order->computed_payment_status==='paid'?'green':($order->computed_payment_status==='partially_paid'?'amber':'red') }}">{{ str($order->computed_payment_status)->replace('_',' ') }}</span>@if($profit)<hr style="border-color:var(--line);margin:12px 0"><p class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.04em">Gross profit</p><p class="{{ $profit['gross_profit']>=0?'kpi-good':'kpi-bad' }}"><b>AED {{ number_format($profit['gross_profit'],2) }}</b> <span class="muted">({{ $profit['margin_percent'] }}% margin)</span></p><p class="muted" style="font-size:12px">Product cost AED {{ number_format($profit['product_cost'],2) }}@if($profit['other_costs_tracked']) · Other costs AED {{ number_format($profit['other_costs'],2) }}@endif</p>@endif</div></div>
@if(auth()->user()->hasPermission('orders.manage'))<form method="post" action="{{ route('orders.simple-status',$order) }}" class="card" style="margin-top:18px">@csrf @method('patch')<div class="form-grid">
<label>Status<select name="simple_status">@foreach(['pending'=>'Pending','ready'=>'Ready','delivered'=>'Delivered','canceled'=>'Canceled'] as $val=>$label)<option value="{{ $val }}" @selected($order->simple_status===$val)>{{ $label }}</option>@endforeach</select></label>
<label>Fulfillment<select name="fulfillment_type"><option value="delivery" @selected($order->fulfillment_type!=='pickup')>Delivery</option><option value="pickup" @selected($order->fulfillment_type==='pickup')>Pickup</option></select></label>
<label>Driver (if Delivery)<select name="driver_id"><option value="">Unassigned</option>@foreach($drivers as $d)<option value="{{ $d->id }}" @selected($order->driver_id===$d->id)>{{ $d->name }}</option>@endforeach</select></label>
<label>Delivery date<input type="date" name="delivery_date" value="{{ $order->delivery_date?->format('Y-m-d') }}"></label>
</div><div class="actions"><button class="btn primary">Update status</button></div></form>@endif
<div class="card" style="margin-top:18px"><h2>Current status</h2><div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$order->simple_status] }}">{{ ucfirst($order->simple_status) }}</span>
<span class="muted">{{ $order->fulfillment_type==='pickup' ? '🚶 Pickup — customer will collect' : '🚚 Driver: '.($order->driver?->name ?? 'Unassigned') }}</span>
</div></div>
<div class="card" style="margin-top:18px"><h2>Customer Share Link</h2>
@php($activeLink=$order->shareLinks()->where('is_active',true)->first())
@if($activeLink)
<p class="muted" style="font-size:12px;word-break:break-all">{{ route('share.show',$activeLink->token) }}</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
<a class="btn small" href="{{ route('share.show',$activeLink->token) }}" target="_blank">Open</a>
<button type="button" class="btn small" data-copy-text="{{ route('share.show',$activeLink->token) }}">Copy</button>
<a class="btn small success" target="_blank" href="https://wa.me/?text={{ urlencode(route('share.show',$activeLink->token)) }}">WhatsApp</a>
@if(auth()->user()->hasPermission('orders.manage'))
<form method="post" action="{{ route('share-links.regenerate',$order) }}" data-confirm="Regenerate the link? The current link will stop working immediately.">@csrf<button type="submit" class="btn small">Regenerate Link</button></form>
<form method="post" action="{{ route('share-links.toggle',$activeLink) }}">@csrf<button type="submit" class="btn small">Disable</button></form>
<form method="post" action="{{ route('share-links.expiry',$activeLink) }}" style="display:inline-flex;gap:4px;align-items:center">@csrf<input type="date" name="expires_at" value="{{ $activeLink->expires_at?->format('Y-m-d') }}"><button type="submit" class="btn small">Set expiry</button></form>
@endif
</div>
<p class="muted" style="font-size:11px;margin-top:8px">Expires: {{ $activeLink->expires_at?->format('d M Y')??'Never' }} · Viewed {{ $activeLink->view_count }} times</p>
@else
<p class="muted">No share link generated yet.</p>
@if(auth()->user()->hasPermission('orders.manage'))
<form method="post" action="{{ route('share-links.store',$order) }}">@csrf<button type="submit" class="btn small primary">Generate Link</button></form>
@endif
@endif
@if($order->shareLinks()->where('is_active',false)->exists())
<details style="margin-top:10px"><summary class="muted" style="font-size:12px;cursor:pointer">Disabled links ({{ $order->shareLinks()->where('is_active',false)->count() }})</summary>
@foreach($order->shareLinks()->where('is_active',false)->latest()->get() as $old)
<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-top:1px solid var(--line)"><span class="muted" style="font-size:11px">{{ $old->created_at->format('d M Y') }}</span>@if(auth()->user()->hasPermission('orders.manage'))<form method="post" action="{{ route('share-links.toggle',$old) }}">@csrf<button type="submit" class="btn small">Enable</button></form>@endif</div>
@endforeach
</details>
@endif
</div>
<div class="card" style="margin-top:18px"><div class="card-header"><h2>Items</h2><span>{{ $order->items->count() }} lines</span></div><div class="table-wrap"><table><thead><tr><th>Image</th><th>Product</th><th>SKU</th><th>Customisation</th><th>Qty</th><th>Rate</th><th>VAT</th><th>Total</th></tr></thead><tbody>@foreach($order->items as $i)<tr><td>@if($i->product?->thumbnail_path)<a href="{{ route('products.edit',$i->product) }}" title="Open product"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($i->product->thumbnail_path) }}" alt="" class="product-thumb"></a>@else<span class="product-thumb-placeholder"></span>@endif</td><td>{{ $i->description }}</td><td>{{ $i->product?->sku??'—' }}</td><td>{{ $i->customisation['notes']??'—' }}</td><td>{{ $i->qty }}</td><td>AED {{ number_format($i->unit_price,2) }}</td><td>AED {{ number_format($i->tax_amount,2) }}</td><td class="amount">AED {{ number_format($i->line_total,2) }}</td></tr>@endforeach</tbody></table></div></div>
<div class="card" style="margin-top:18px"><h2>Attachments</h2>
@if(auth()->user()->hasPermission('orders.manage'))
<form method="post" action="{{ route('orders.attachments.store',$order) }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">@csrf<label>Category<select name="category" required><option value="">Select</option>@foreach(\App\Models\OrderAttachment::CATEGORIES as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach</select></label><label>File<input type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.pdf"></label><button class="btn small primary">Upload</button></form>
@endif
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Category</th><th>File</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead><tbody>@forelse($order->attachments->sortByDesc('created_at') as $a)<tr><td><span class="badge blue">{{ $a->category }}</span></td><td><a href="{{ route('order-attachments.download',$a) }}">{{ $a->original_name }}</a></td><td>{{ $a->uploader?->name??'—' }}</td><td>{{ $a->created_at->format('d M Y') }}</td><td>@if(auth()->user()->hasPermission('orders.delete')||$a->uploaded_by===auth()->id())<form method="post" action="{{ route('order-attachments.destroy',$a) }}" data-confirm="Remove this attachment?">@csrf @method('delete')<button type="submit" class="link">Remove</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="empty">No attachments yet.</td></tr>@endforelse</tbody></table></div>
</div>
<div class="card" style="margin-top:18px"><h2>Internal comments</h2><p class="muted" style="font-size:11px">Staff-only — never shown on customer documents</p>
<form method="post" action="{{ route('orders.comments.store',$order) }}" style="margin-top:12px"><textarea name="body" placeholder="Add a comment... use @name to mention a teammate" required></textarea><div class="actions"><button class="btn primary small">Post comment</button></div></form>
<div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
@forelse($order->comments->sortByDesc('created_at') as $c)
<div class="health" style="align-items:flex-start">
<div style="flex:1"><b>{{ $c->user->name }}</b> <span class="muted">{{ $c->created_at->format('d M Y, h:i A') }}@if($c->edited_at) · edited @endif</span>
<p style="margin:4px 0 0">{!! preg_replace('/@([a-zA-Z0-9._-]+)/', '<span class="badge blue">@$1</span>', e($c->body)) !!}</p>
@if($c->user_id===auth()->id()||auth()->user()->hasPermission('orders.delete'))
<form method="post" action="{{ route('order-comments.destroy',$c) }}" style="display:inline" data-confirm="Delete this comment?">@csrf @method('delete')<button type="submit" class="link" style="font-size:11px">Delete</button></form>
@endif
</div>
</div>
@empty
<p class="muted">No comments yet.</p>
@endforelse
</div>
</div>
<div class="card" style="margin-top:18px"><h2>Activity timeline</h2>@forelse($order->statusHistory->sortByDesc('created_at') as $h)<div class="health"><span class="dot {{ in_array($h->field,['payment','invoice'])?'ok':'warning' }}"></span><div><b>{{ ucfirst(str_replace('_',' ',$h->field)) }}: @if($h->old_value)"{{ str_replace('_',' ',$h->old_value) }}" → @endif"{{ str_replace('_',' ',$h->new_value) }}"</b><div class="muted">{{ $h->changedBy?->name??'System' }} · {{ $h->created_at->format('d M Y H:i') }}</div></div></div>@empty<p class="muted">No status changes yet.</p>@endforelse
<div class="health"><span class="dot ok"></span><div><b>Order created</b><div class="muted">{{ $order->created_by ? \App\Models\User::find($order->created_by)?->name : 'System' }} · {{ $order->created_at->format('d M Y H:i') }}</div></div></div>
</div>@endsection
