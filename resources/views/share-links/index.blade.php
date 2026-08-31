@extends('layouts.app')
@section('title','Customer Share Links')
@section('subtitle','All generated customer-facing share links')
@section('content')
<div class="toolbar"><form><input name="q" value="{{ request('q') }}" placeholder="Order number or customer"><select name="status"><option value="">All</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Disabled</option></select><button class="btn">Filter</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Views</th><th>Last viewed</th><th>Expires</th><th>Created</th><th>Actions</th></tr></thead><tbody>
@forelse($links as $l)
<tr><td><a href="{{ route('orders.show',$l->order) }}">{{ $l->order->order_number }}</a></td><td>{{ $l->order->customer->name }}</td><td><span class="badge {{ $l->is_active?'green':'red' }}">{{ $l->is_active?'Active':'Disabled' }}</span></td><td>{{ $l->view_count }}</td><td>{{ $l->last_viewed_at?->format('d M Y, h:i A')??'Never' }}</td><td>{{ $l->expires_at?->format('d M Y')??'Never' }}</td><td>{{ $l->created_at->format('d M Y') }}</td><td><a class="btn small" href="{{ route('share.show',$l->token) }}" target="_blank">Open</a></td></tr>
@empty
<tr><td colspan="8" class="empty">No share links generated yet.</td></tr>
@endforelse
</tbody></table></div>{{ $links->links() }}
@endsection
