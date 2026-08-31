@extends('layouts.app')
@section('title','Dashboard')
@section('subtitle','Live business overview for the selected commercial month')
@section('content')
<div class="dashboard-layout">
<div class="dashboard-main">
<div class="dash-greeting"><h1>{{ $greeting }}</h1><p>{{ now('Asia/Dubai')->format('l, d F Y') }} · {{ number_format($stats['orders']) }} orders and AED {{ number_format($stats['sales'],0) }} in sales this month, {{ $stats['due_today'] }} {{ $stats['due_today']===1?'delivery':'deliveries' }} due today.</p></div>
<div class="toolbar"><form method="get"><label>Commercial month<input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"></label></form><div>@if(auth()->user()->hasPermission('quotations.manage'))<a class="btn" href="{{ route('quotations.create') }}">New quotation</a>@endif @if(auth()->user()->hasPermission('orders.manage'))<a class="btn primary" href="{{ route('orders.create') }}">New order</a>@endif</div></div>
<div class="grid stats">
 <div class="stat"><small>Orders this month</small><strong>{{ number_format($stats['orders']) }}</strong><em>Changes with selected month</em></div>
 <div class="stat"><small>Sales value</small><strong>AED {{ number_format($stats['sales'],2) }}</strong><em>Order value for this month</em>@include('partials._sparkline',['values'=>$chartData['monthly']->pluck('revenue')->all(),'color'=>'#22d3ee'])</div>
 <div class="stat"><small>Outstanding invoices</small><strong class="kpi-bad">AED {{ number_format($stats['unpaid'],2) }}</strong><em>All unpaid balances</em></div>
 <div class="stat"><small>Month expenses</small><strong>AED {{ number_format($stats['expenses'],2) }}</strong><em>Posted operating expenses</em>@include('partials._sparkline',['values'=>$chartData['monthly']->pluck('expenses')->all(),'color'=>'#f0556f'])</div>
 <div class="stat"><small>Deliveries today</small><strong>{{ $stats['due_today'] }}</strong></div><div class="stat"><small>Active production</small><strong>{{ $stats['production'] }}</strong></div><div class="stat"><small>Total customers</small><strong>{{ $stats['customers'] }}</strong></div><div class="stat"><small>Current net order value</small><strong>AED {{ number_format($stats['sales']-$stats['expenses'],2) }}</strong></div>
 <div class="stat"><small>Gross profit</small><strong class="{{ $stats['gross_profit']>=0?'kpi-good':'kpi-bad' }}">AED {{ number_format($stats['gross_profit'],2) }}</strong><em>{{ $stats['margin_percent'] }}% margin this month</em></div>
 <div class="stat"><small>Receivables</small><strong class="kpi-bad">AED {{ number_format($stats['receivables'],2) }}</strong><em>From posted journal entries</em></div>
 <div class="stat"><small>Low stock products</small><strong class="{{ $stats['low_stock']>0?'kpi-bad':'' }}">{{ $stats['low_stock'] }}</strong><em>At or below reorder level</em></div>
</div>
<div class="grid cols-2">
<div class="card"><div class="card-header"><h2>Recent sales orders</h2><a href="{{ route('orders.index') }}">View all</a></div><div class="table-wrap mobile-cards dashboard-compact-table"><table><thead><tr><th>Order</th><th>Customer</th><th>Delivery</th><th>Status</th><th>Total</th></tr></thead><tbody>@forelse($recentOrders as $o)<tr><td data-label="Order"><a href="{{ route('orders.show',$o) }}"><b>{{ $o->order_number }}</b></a></td><td data-label="Customer" class="truncate-cell" title="{{ $o->customer->name }}">{{ $o->customer->name }}</td><td data-label="Delivery">{{ $o->delivery_date?->format('d M Y')??'Not set' }}</td><td data-label="Status"><span class="badge {{ ['pending'=>'amber','ready'=>'blue','delivered'=>'green','canceled'=>'red'][$o->simple_status] ?? 'amber' }}">{{ ucfirst($o->simple_status) }}</span></td><td data-label="Total" class="amount">AED {{ number_format($o->grand_total,2) }}</td></tr>@empty<tr><td colspan="5" class="empty">No orders yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-header"><h2>Today's delivery schedule</h2><a href="{{ route('deliveries.index',['date'=>today()->toDateString()]) }}">Open schedule</a></div>@forelse($deliveries as $d)<div class="health"><span class="dot {{ $d->status==='delivered'?'ok':'warning' }}"></span><div><b>{{ $d->salesOrder->order_number }} · {{ $d->customer->name }}</b><div class="muted">{{ $d->status }} · {{ $d->customer->emirate }}</div></div></div>@empty<div class="empty">No deliveries scheduled today.</div>@endforelse</div>
</div>

<section class="dashboard-analytics" aria-labelledby="analytics-title">
    <div class="section-heading"><div><h2 id="analytics-title">Business analytics</h2><p>Live figures for the selected month and recent trends</p></div><span class="analytics-period">{{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('F Y') }}</span></div>
    <div class="grid analytics-primary">
        <article class="card chart-card chart-wide">
            <div class="chart-title"><div><h2>Revenue vs expenses</h2><p>Last 6 months · AED</p></div><div class="chart-key"><span><i class="key-revenue"></i>Revenue</span><span><i class="key-expense"></i>Expenses</span></div></div>
            <div class="chart-frame"><canvas data-dashboard-chart="monthly" aria-label="Revenue and expenses for the last six months"></canvas><div class="chart-empty" hidden>No financial data yet</div></div>
        </article>
        <article class="card chart-card">
            <div class="chart-title"><div><h2>Sales by emirate</h2><p>Selected month</p></div></div>
            <div class="chart-frame donut-frame"><canvas data-dashboard-chart="emirates" aria-label="Sales by emirate"></canvas><div class="chart-empty" hidden>No sales data yet</div></div>
            <div class="chart-legend" data-chart-legend="emirates"></div>
        </article>
    </div>
    <div class="grid analytics-secondary">
        <article class="card chart-card top-products-card">
            <div class="chart-title"><div><h2>Top products</h2><p>Selected month · AED</p></div></div>
            @php($topMax=max(1,(float)collect($chartData['top_products'])->max('total')))
            <div class="product-bars">
                @forelse($chartData['top_products'] as $product)
                    <div class="product-bar"><div><span title="{{ $product['label'] }}">{{ $product['label'] }}</span><b>{{ number_format($product['total'],0) }}</b></div><div class="bar-track"><i style="width:{{ max(4,($product['total']/$topMax)*100) }}%"></i></div></div>
                @empty<div class="chart-empty static">No product sales yet</div>@endforelse
            </div>
        </article>
        <article class="card chart-card">
            <div class="chart-title"><div><h2>Payment methods</h2><p>Selected month</p></div></div>
            <div class="chart-frame donut-frame compact"><canvas data-dashboard-chart="payments" aria-label="Payments by method"></canvas><div class="chart-empty" hidden>No payment data yet</div></div>
            <div class="chart-legend" data-chart-legend="payments"></div>
        </article>
        <article class="card chart-card">
            <div class="chart-title"><div><h2>Profit trend</h2><p>Sales less operating expenses · AED</p></div></div>
            <div class="chart-frame"><canvas data-dashboard-chart="profit" aria-label="Profit trend for the last six months"></canvas><div class="chart-empty" hidden>No financial data yet</div></div>
        </article>
    </div>
</section>
<script type="application/json" id="dashboard-chart-data">@json($chartData)</script>
</div>
<aside class="dashboard-ai-col">@include('partials._ivory-ai-panel')</aside>
</div>
@endsection
