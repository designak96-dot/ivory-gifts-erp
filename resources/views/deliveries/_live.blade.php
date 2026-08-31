<div class="grid delivery-kpis">
<div class="stat"><small>Total orders — {{ $calendarMonth->format('F Y') }}</small><strong>{{ $stats['month_total'] }}</strong></div>
</div>

<div class="quick-dropdown-bar">
<div class="quick-dropdown">
<button type="button" class="stat quick-dropdown-trigger {{ $stats['overdue']>0?'danger-stat':'' }}" data-toggle-dropdown="qd-overdue"><small>Overdue</small><strong>{{ $stats['overdue'] }}</strong></button>
<div class="quick-dropdown-panel" id="qd-overdue" hidden>
@forelse($quickLists['overdue'] as $d)
<a href="{{ route('deliveries.show',$d) }}" class="quick-dropdown-row"><b>{{ $d->salesOrder->order_number }}</b><span>{{ $d->customer->name }}</span><span>{{ $d->delivery_date?->format('d M Y') }}</span><span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$d->salesOrder->simple_status] ?? 'amber' }}">{{ ucfirst($d->salesOrder->simple_status) }}</span><span class="badge {{ $d->salesOrder->simple_design==='designed'?'green':'red' }}">{{ $d->salesOrder->simple_design==='designed'?'Designed':'Need Designer' }}</span><span class="badge {{ ['not_confirmed'=>'red','waiting_deposit'=>'blue','confirmed'=>'green'][$d->salesOrder->simple_confirmation] ?? 'red' }}">{{ str($d->salesOrder->simple_confirmation)->replace('_',' ')->title() }}</span></a>
@empty
<p class="muted" style="padding:10px">No overdue active orders.</p>
@endforelse
</div>
</div>

<div class="quick-dropdown">
<button type="button" class="stat quick-dropdown-trigger waiting-stat" data-toggle-dropdown="qd-waiting-deposit"><small>Waiting Deposit</small><strong>{{ $stats['waiting_deposit'] }}</strong></button>
<div class="quick-dropdown-panel" id="qd-waiting-deposit" hidden>
@forelse($quickLists['waiting_deposit'] as $d)
<a href="{{ route('deliveries.show',$d) }}" class="quick-dropdown-row"><b>{{ $d->salesOrder->order_number }}</b><span>{{ $d->customer->name }}</span><span>{{ $d->delivery_date?->format('d M Y') }}</span><span class="badge {{ $d->salesOrder->simple_design==='designed'?'green':'red' }}">{{ $d->salesOrder->simple_design==='designed'?'Designed':'Need Designer' }}</span><span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$d->salesOrder->simple_status] ?? 'amber' }}">{{ ucfirst($d->salesOrder->simple_status) }}</span></a>
@empty
<p class="muted" style="padding:10px">No orders waiting for deposit.</p>
@endforelse
</div>
</div>

<div class="quick-dropdown">
<button type="button" class="stat quick-dropdown-trigger {{ $stats['need_design']>0?'design-stat':'' }}" data-toggle-dropdown="qd-need-design"><small>Need Design — Overdue + Next 10 Days</small><strong>{{ $stats['need_design'] }}</strong></button>
<div class="quick-dropdown-panel" id="qd-need-design" hidden data-need-design-panel>
@forelse($quickLists['need_design'] as $d)
<div class="quick-dropdown-row" data-need-design-row data-delivery-id="{{ $d->id }}">
<a href="{{ route('deliveries.show',$d) }}"><b>{{ $d->salesOrder->order_number }}</b></a>
<span>{{ $d->customer->name }}</span>
<span class="{{ $d->delivery_date && $d->delivery_date->isBefore(today()) ? 'kpi-bad' : '' }}">{{ $d->delivery_date?->format('d M Y') }}</span>
<span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$d->salesOrder->simple_status] ?? 'amber' }}">{{ ucfirst($d->salesOrder->simple_status) }}</span>
<form method="post" action="{{ route('deliveries.order-workflow',$d) }}" data-need-design-form>@csrf @method('patch')<input type="hidden" name="field" value="design"><select name="value" class="mini-status" data-need-design-select><option value="need_designer" selected>Need Designer</option><option value="designed">Designed</option></select></form>
</div>
@empty
<p class="muted" style="padding:10px">No Need Designer orders overdue or in the next 10 days.</p>
@endforelse
</div>
</div>
</div>

<section class="card delivery-workspace">
<form method="get" class="delivery-main-filters"><input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}"><label>Date<input type="date" name="date" value="{{ request('date') }}"></label><label>Delivery location<select name="location"><option value="">All locations</option>@foreach($locations as $location)<option value="{{ $location }}" @selected(request('location')===$location)>{{ $location }}</option>@endforeach</select></label><label class="delivery-search">Search<input name="q" value="{{ request('q') }}" placeholder="Order, customer, phone, area or address"></label><button class="btn primary">Apply</button><a class="btn" href="{{ route('deliveries.index',['month'=>$calendarMonth->format('Y-m')]) }}">Reset</a></form>
<nav class="delivery-tabs"><a @class(['active'=>!request('scope')||request('scope')==='working']) href="{{ route('deliveries.index',array_filter(['scope'=>'working','month'=>$calendarMonth->format('Y-m'),'date'=>request('date'),'location'=>request('location'),'q'=>request('q')])) }}">Working list</a><a @class(['active'=>request('scope')==='overdue']) href="{{ route('deliveries.index',['scope'=>'overdue','month'=>$calendarMonth->format('Y-m')]) }}">Overdue ({{ $stats['overdue'] }})</a><a @class(['active'=>request('scope')==='today']) href="{{ route('deliveries.index',['scope'=>'today','month'=>$calendarMonth->format('Y-m')]) }}">Today</a><a @class(['active'=>request('scope')==='all']) href="{{ route('deliveries.index',['scope'=>'all','month'=>$calendarMonth->format('Y-m')]) }}">All orders</a><span class="live-indicator"><i></i>Updates automatically every 12 seconds</span></nav>
<div class="table-wrap delivery-dashboard-table"><table><thead><tr><th>Priority</th><th>Order</th><th>Customer</th><th>Date</th><th>Location / Area</th><th>Confirmation</th><th>Design</th><th>Driver</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($deliveries as $delivery)
@php
$order=$delivery->salesOrder;$customer=$delivery->customer;$overdue=$delivery->delivery_date&&$delivery->delivery_date->isBefore(today())&&!in_array($delivery->status,['delivered','returned']);$days=$overdue?$delivery->delivery_date->diffInDays(today()):($delivery->delivery_date?->diffInDays(today(),false));$phone=$order->customer_phone?:$customer->phone;$area=$customer->area;$address=$order->delivery_address?:$customer->delivery_address;
@endphp
<tr @class(['overdue-row'=>$overdue,'very-urgent-row'=>$order->is_very_urgent])>
<td data-label="Priority">@if($order->is_very_urgent)<span class="priority-box urgent">●<b>Very urgent</b><small>{{ $overdue?$days.' days past':($delivery->delivery_date?->isToday()?'Today':abs((int)$days).' days left') }}</small></span>@elseif($overdue)<span class="priority-box overdue">⏱<b>{{ $days }} days past</b></span>@else<span class="priority-box"><b>{{ ucfirst($order->priority) }}</b><small>{{ $delivery->delivery_date?->isToday()?'Today':abs((int)$days).' days left' }}</small></span>@endif</td>
<td data-label="Order"><a href="{{ route('orders.show',$order) }}"><b>#{{ $order->order_number }}</b></a><small>{{ $order->is_legacy_delivery_import?'Imported history':'Manual · '.$order->order_month?->format('M Y') }}</small>@unless($isDriverOnly)@include('partials._confirmed-proof-widget',['order'=>$order])@endunless</td>
<td data-label="Customer"><b>{{ $customer->name }}</b><small>{{ $phone?:'No phone' }}</small></td>
<td data-label="Date"><b>{{ $delivery->delivery_date?->format('d M')??'Not set' }}</b><small>{{ $delivery->delivery_date?->format('Y') }}</small></td>
<td data-label="Location"><b>{{ $order->emirate?:$customer->emirate?:'Not set' }}</b><small title="{{ $address }}">{{ $area?str($area)->limit(25):str($address)->limit(34) }}</small></td>
<td data-label="Confirmation">@if($order->simple_status==='canceled')<span class="badge red">N/A</span>@elseif(!$isDriverOnly)<form method="post" action="{{ route('deliveries.order-workflow',$delivery) }}">@csrf @method('patch')<input type="hidden" name="field" value="confirmation"><select name="value" class="mini-status status-{{ $order->simple_confirmation }}" onchange="this.form.submit()"><option value="not_confirmed" @selected($order->simple_confirmation==='not_confirmed')>Not Confirmed</option><option value="waiting_deposit" @selected($order->simple_confirmation==='waiting_deposit')>Waiting For Deposit</option><option value="confirmed" @selected($order->simple_confirmation==='confirmed')>Confirmed</option></select></form>@else<span class="badge {{ ['not_confirmed'=>'red','waiting_deposit'=>'blue','confirmed'=>'green'][$order->simple_confirmation] }}">{{ str($order->simple_confirmation)->replace('_',' ')->title() }}</span>@endif</td>
<td data-label="Design">@if($order->simple_status==='canceled')<span class="badge red">N/A</span>@elseif(!$isDriverOnly)<form method="post" action="{{ route('deliveries.order-workflow',$delivery) }}">@csrf @method('patch')<input type="hidden" name="field" value="design"><select name="value" class="mini-status" onchange="this.form.submit()"><option value="need_designer" @selected($order->simple_design==='need_designer')>Need Designer</option><option value="designed" @selected($order->simple_design==='designed')>Designed</option></select></form>@else<span class="badge {{ $order->simple_design==='designed'?'green':'red' }}">{{ $order->simple_design==='designed'?'Designed':'Need Designer' }}</span>@endif</td>
<td data-label="Driver">@if($order->fulfillment_type==='pickup')<span class="badge blue">Pickup</span>@elseif(!$isDriverOnly)<form method="post" action="{{ route('deliveries.quick-update',$delivery) }}">@csrf @method('patch')<select name="driver_id" class="mini-status" onchange="this.form.submit()"><option value="">Unassigned</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected($delivery->driver_id===$driver->id)>{{ $driver->name }}</option>@endforeach</select></form>@else{{ $delivery->driver?->name??'Unassigned' }}@endif</td>
<td data-label="Status">@if(!$isDriverOnly)<form method="post" action="{{ route('deliveries.order-workflow',$delivery) }}">@csrf @method('patch')<input type="hidden" name="field" value="status"><select name="value" class="mini-status status-{{ $order->simple_status }}" onchange="this.form.submit()">@foreach(['pending'=>'Pending','ready'=>'Ready','delivered'=>'Delivered','canceled'=>'Canceled'] as $val=>$label)<option value="{{ $val }}" @selected($order->simple_status===$val)>{{ $label }}</option>@endforeach</select></form>@else<span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$order->simple_status] ?? 'amber' }}">{{ ucfirst($order->simple_status) }}</span>@endif @if(in_array($order->simple_status,['ready','delivered']))<button type="button" class="btn small success" data-whatsapp-share data-check-url="{{ route('whatsapp.check',$order) }}" data-link-url="{{ route('whatsapp.link',$order) }}" title="Share via WhatsApp" style="padding:2px 7px;margin-top:4px">💬</button>@endif</td>
<td data-label="Actions"><div class="row-actions"><a class="btn small" href="{{ route('deliveries.show',$delivery) }}" title="Delivery details">Delivery</a>@if(!$order->is_legacy_delivery_import&&auth()->user()->hasPermission('orders.manage'))<a class="btn small primary" href="{{ route('orders.edit',$order) }}">Edit order</a>@endif</div></td>
</tr>
@empty<tr><td colspan="10" class="empty">No deliveries match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $deliveries->links() }}
</section>
