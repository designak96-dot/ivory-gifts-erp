<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Dashboard') · {{ $companyName }}</title>
    <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}?v={{ @filemtime(public_path('build/assets/app.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/sync.css') }}?v={{ @filemtime(public_path('build/assets/sync.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/dashboard.css') }}?v={{ @filemtime(public_path('build/assets/dashboard.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/delivery.css') }}?v={{ @filemtime(public_path('build/assets/delivery.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/order-entry.css') }}?v={{ @filemtime(public_path('build/assets/order-entry.css')) ?: time() }}">
</head>
<body>
@php($u=auth()->user())
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="sidebar-logo">@else<span class="brand-mark">IG</span>@endif<span><b>{{ $companyName ?? 'Ivory Gifts' }}</b><small>ERP & Accounts</small></span></a>
        <nav class="nav">
            @if($u->hasPermission('dashboard.view'))<a @class(['active'=>request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">Dashboard</a>@endif
            <p>Sales</p>
            @if($u->hasPermission('quotations.view'))<a @class(['active'=>request()->routeIs('quotations.*')]) href="{{ route('quotations.index') }}">Quotations</a>@endif
            @if($u->hasPermission('orders.view'))<a @class(['active'=>request()->routeIs('orders.*')]) href="{{ route('orders.index') }}">Sales Orders</a>@endif
            @if($u->hasPermission('invoices.view'))<a @class(['active'=>request()->routeIs('invoices.*')||request()->routeIs('payments.*')]) href="{{ route('invoices.index') }}">Invoices & Payments</a>@endif
            @if($u->hasPermission('customers.view'))<a @class(['active'=>request()->routeIs('customers.*')]) href="{{ route('customers.index') }}">Customers</a>@endif
            <p>Operations</p>
            
            @if($u->hasPermission('deliveries.view'))<a @class(['active'=>request()->routeIs('deliveries.*')]) href="{{ route('deliveries.index') }}">Deliveries</a>@endif
            @if($u->hasPermission('products.view'))<a @class(['active'=>request()->routeIs('products.*')]) href="{{ route('products.index') }}">Products</a>@endif
            @if($u->hasPermission('inventory.view'))<a @class(['active'=>request()->routeIs('inventory.*')]) href="{{ route('inventory.index') }}">Inventory</a>@endif
            @if($u->hasPermission('purchases.view'))<a @class(['active'=>request()->routeIs('purchases.*','suppliers.*')]) href="{{ route('purchases.index') }}">Purchases & Suppliers</a>@endif
            <p>Finance</p>
            @if($u->hasPermission('expenses.view'))<a @class(['active'=>request()->routeIs('expenses.*')]) href="{{ route('expenses.index') }}">Expenses</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('accounting.*')]) href="{{ route('accounting.index') }}">Accounting</a>@endif
            @if($u->hasPermission('reports.view'))<a @class(['active'=>request()->routeIs('reports.*')]) href="{{ route('reports.index') }}">Reports</a>@endif
            @if($u->hasPermission('users.manage')||$u->hasPermission('settings.manage')||$u->hasPermission('system.view'))<p>Administration</p>@endif
            @if($u->hasPermission('users.manage'))<a @class(['active'=>request()->routeIs('users.*')]) href="{{ route('users.index') }}">Users & Roles</a>@endif
            @if($u->hasPermission('settings.manage'))<a @class(['active'=>request()->routeIs('settings.*')]) href="{{ route('settings.index') }}">Settings</a>@endif
            @if($u->hasPermission('audit.view'))<a @class(['active'=>request()->routeIs('system.audit')]) href="{{ route('system.audit') }}">Audit Log</a>@endif
            @if($u->hasPermission('system.view'))<a @class(['active'=>request()->routeIs('system.health','system.import-export','backups.*')]) href="{{ route('system.health') }}">System</a>@endif
            @if($u->hasPermission('imports.manage'))<a @class(['active'=>request()->routeIs('imports.*')]) href="{{ route('imports.create') }}">Import Customers/Orders</a>@endif
            @if($u->hasPermission('imports.manage'))<a @class(['active'=>request()->routeIs('products.import.*')]) href="{{ route('products.import.create') }}">Import Products</a>@endif
        </nav>
    </aside>
    <main class="main">
        <header class="topbar"><button class="menu" data-sidebar>☰</button><div><h1>@yield('title','Dashboard')</h1><small>@yield('subtitle','Ivory Gifts operations at a glance')</small></div><div class="topbar-meta"><div class="live-clock" data-live-clock><strong data-live-time>{{ now('Asia/Dubai')->format('h:i:s A') }}</strong><small data-live-date>{{ now('Asia/Dubai')->format('D, d M Y') }} · UAE time</small></div><div class="user"><span>{{ $u->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="link">Sign out</button></form></div></div></header>
        <section class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert danger"><b>Please check the following:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
</div>
<nav class="mobile-nav"><a href="{{ route('dashboard') }}">Home</a>@if($u->hasPermission('orders.view'))<a href="{{ route('orders.index') }}">Orders</a>@endif @if($u->hasPermission('deliveries.view'))<a href="{{ route('deliveries.index') }}">Delivery</a>@endif<button data-sidebar>More</button></nav>
<dialog id="proof-viewer" class="quick-dialog"><div class="dialog-head"><h2>Proof</h2><button type="button" class="dialog-close" data-close-dialog>×</button></div><iframe data-proof-viewer-frame src="about:blank" style="width:100%;height:70vh;border:1px solid var(--line);border-radius:9px;background:#eef0f9" title="Proof document"></iframe></dialog>
<script>window.IVORY_SYNC_URL=@json(route('sync.version'));(()=>{const c=document.querySelector('[data-live-clock]');if(!c)return;const t=c.querySelector('[data-live-time]'),d=c.querySelector('[data-live-date]'),tf=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Dubai',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}),df=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Dubai',weekday:'short',day:'2-digit',month:'short',year:'numeric'}),update=()=>{const n=new Date();t.textContent=tf.format(n);d.textContent=df.format(n)+' · UAE time'};update();setInterval(update,1000)})();</script><script src="{{ asset('build/assets/app.js') }}?v={{ @filemtime(public_path('build/assets/app.js')) ?: time() }}" defer></script>@stack('scripts')
</body></html>
