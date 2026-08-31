@extends('layouts.app')
@section('title',$customer->name)
@section('subtitle',$customer->customer_code.' · '.($customer->company_name?:'Customer account'))
@section('content')
<div class="toolbar"><div>@if($customer->whatsapp_url)<a class="btn success" target="_blank" rel="noopener" href="{{ $customer->whatsapp_url }}?text={{ urlencode('Hello '.$customer->name.', this is Ivory Gifts.') }}">Open WhatsApp</a>@endif</div>@if(auth()->user()->hasPermission('customers.manage'))<a class="btn primary" href="{{ route('customers.edit',$customer) }}">Edit customer</a>@endif</div>
<div class="grid cols-3"><div class="card"><h2>Contact</h2><p>{{ $customer->phone?:'No phone' }}<br>{{ $customer->email?:'No email' }}<br>{{ $customer->preferred_language==='ar'?'Arabic':'English' }}</p></div><div class="card"><h2>Delivery</h2><p>{{ $customer->delivery_address?:'No delivery address' }}<br>{{ collect([$customer->area,$customer->emirate])->filter()->join(', ') }}</p></div><div class="card"><h2>Commercial terms</h2><p>{{ $customer->payment_terms_days }} day terms<br>Credit limit: AED {{ number_format($customer->credit_limit,2) }}<br><span class="badge green">{{ $customer->status }}</span></p></div></div>
<div class="card" style="margin-top:18px"><h2>Customer lifetime value</h2>
<div class="grid cols-4" style="margin-top:15px">
<div class="stat"><small>Total orders</small><strong>{{ $lifetimeValue['total_orders'] }}</strong></div>
<div class="stat"><small>Total revenue</small><strong>AED {{ number_format($lifetimeValue['total_revenue'],2) }}</strong></div>
<div class="stat"><small>Total profit</small><strong class="{{ $lifetimeValue['total_profit']>=0?'kpi-good':'kpi-bad' }}">AED {{ number_format($lifetimeValue['total_profit'],2) }}</strong></div>
<div class="stat"><small>Average order value</small><strong>AED {{ number_format($lifetimeValue['average_order_value'],2) }}</strong></div>
<div class="stat"><small>First order</small><strong>{{ $lifetimeValue['first_order']?->format('d M Y')??'—' }}</strong></div>
<div class="stat"><small>Last order</small><strong>{{ $lifetimeValue['last_order']?->format('d M Y')??'—' }}</strong></div>
<div class="stat"><small>Order frequency</small><strong>{{ $lifetimeValue['order_frequency_days']!==null ? 'Every '.$lifetimeValue['order_frequency_days'].' days' : '—' }}</strong></div>
<div class="stat"><small>Outstanding amount</small><strong class="kpi-bad">AED {{ number_format($lifetimeValue['outstanding_amount'],2) }}</strong></div>
</div>
</div>
<div class="card" style="margin-top:18px"><div class="card-header"><h2>Tags</h2></div>
@if(auth()->user()->hasPermission('customers.manage'))
<form method="post" action="{{ route('customers.tags',$customer) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
@foreach($allTags as $t)<label class="check" style="margin-right:4px"><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked($customer->tags->contains($t))> <span class="badge {{ $t->color }}">{{ $t->name }}</span></label>@endforeach
<button class="btn small primary">Save tags</button>
</form>
@else
@forelse($customer->tags as $t)<span class="badge {{ $t->color }}">{{ $t->name }}</span>@empty<span class="muted">No tags.</span>@endforelse
@endif
</div>
<div class="grid cols-3" style="margin-top:18px"><div class="card"><div class="card-header"><h2>Quotations</h2><b>{{ $customer->quotations()->count() }}</b></div><p class="amount">AED {{ number_format($customer->quotations()->sum('grand_total'),2) }}</p></div><div class="card"><div class="card-header"><h2>Orders</h2><b>{{ $customer->orders()->count() }}</b></div><p class="amount">AED {{ number_format($customer->orders()->where('is_legacy_delivery_import',false)->sum('grand_total'),2) }}</p></div><div class="card"><div class="card-header"><h2>Outstanding</h2><b>{{ $customer->invoices()->where('outstanding_amount','>',0)->count() }}</b></div><p class="amount kpi-bad">AED {{ number_format($customer->invoices()->sum('outstanding_amount'),2) }}</p></div></div>

<div class="card" style="margin-top:18px"><h2>Order history</h2><div class="table-wrap mobile-cards" style="margin-top:15px"><table><thead><tr><th>Order</th><th>Order date</th><th>Delivery date</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Payment status</th><th>Order status</th></tr></thead><tbody>
@forelse($customer->orders()->where('is_legacy_delivery_import',false)->latest('order_date')->get() as $order)
<tr><td data-label="Order"><a href="{{ route('orders.show',$order) }}"><b>{{ $order->order_number }}</b></a></td><td data-label="Order date">{{ $order->order_date->format('d M Y') }}</td><td data-label="Delivery date">{{ $order->delivery_date?->format('d M Y')??'Not set' }}</td><td data-label="Total" class="amount">AED {{ number_format($order->grand_total,2) }}</td><td data-label="Paid" class="amount kpi-good">AED {{ number_format($order->paid_amount,2) }}</td><td data-label="Remaining" class="amount kpi-bad">AED {{ number_format($order->remaining_amount,2) }}</td><td data-label="Payment status"><span class="badge {{ $order->computed_payment_status==='paid'?'green':($order->computed_payment_status==='partially_paid'?'amber':'red') }}">{{ str($order->computed_payment_status)->replace('_',' ') }}</span></td><td data-label="Order status"><span class="badge {{ $order->delivery_status==='delivered'?'green':'amber' }}">{{ str($order->delivery_status)->replace('_',' ') }}</span></td></tr>
@empty
<tr><td colspan="8" class="empty">No orders yet for this customer.</td></tr>
@endforelse
</tbody></table></div></div>
@if($favouriteProducts->count())
<div class="card" style="margin-top:18px"><h2>Favourite products</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Product</th><th>Times ordered</th><th>Total qty</th></tr></thead><tbody>@foreach($favouriteProducts as $row)<tr><td><b>{{ $row->product?->name_en??'—' }}</b><br><span class="muted">{{ $row->product?->sku }}</span></td><td>{{ $row->order_count }}</td><td>{{ number_format($row->total_qty,2) }}</td></tr>@endforeach</tbody></table></div></div>
@endif
@endsection
