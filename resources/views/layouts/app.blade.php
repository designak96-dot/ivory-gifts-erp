<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <script>(function(){var t=localStorage.getItem('ivory-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark')})();</script>
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Dashboard') · {{ $companyName }}</title>
    <link rel="stylesheet" href="{{ asset('build/assets/app.css') }}?v={{ @filemtime(public_path('build/assets/app.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/sync.css') }}?v={{ @filemtime(public_path('build/assets/sync.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/dashboard.css') }}?v={{ @filemtime(public_path('build/assets/dashboard.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/delivery.css') }}?v={{ @filemtime(public_path('build/assets/delivery.css')) ?: time() }}"><link rel="stylesheet" href="{{ asset('build/assets/order-entry.css') }}?v={{ @filemtime(public_path('build/assets/order-entry.css')) ?: time() }}">
</head>
<body>
@php($u=auth()->user())
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="sidebar-logo">@else<span class="brand-mark">IG</span>@endif<span><b>{{ $companyName ?? 'Ivory Gifts' }}</b><small>ERP & Accounts</small></span></a>
        <nav class="nav">
            @if($u->hasPermission('dashboard.view'))<a @class(['active'=>request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">@include('partials._nav-icon',['name'=>'dashboard']) Dashboard</a>@endif
            @if($u->hasPermission('dashboard.view'))<a @class(['active'=>request()->routeIs('ivory-ai.*')]) href="{{ route('ivory-ai.index') }}">@include('partials._nav-icon',['name'=>'reports']) Ivory AI</a>@endif
            <p>Sales</p>
            @if($u->hasPermission('quotations.view'))<a @class(['active'=>request()->routeIs('quotations.*')]) href="{{ route('quotations.index') }}">@include('partials._nav-icon',['name'=>'quotations']) Quotations</a>@endif
            @if($u->hasPermission('orders.view'))<a @class(['active'=>request()->routeIs('orders.*')]) href="{{ route('orders.index') }}">@include('partials._nav-icon',['name'=>'orders']) Sales Orders</a>@endif
            @if($u->hasPermission('invoices.view'))<a @class(['active'=>request()->routeIs('invoices.*')||request()->routeIs('payments.*')]) href="{{ route('invoices.index') }}">@include('partials._nav-icon',['name'=>'invoices']) Invoices & Payments</a>@endif
            @if($u->hasPermission('invoices.view'))<a @class(['active'=>request()->routeIs('credit-notes.*')]) href="{{ route('credit-notes.index') }}">@include('partials._nav-icon',['name'=>'invoices']) Credit Notes</a>@endif
            @if($u->hasPermission('orders.view'))<a @class(['active'=>request()->routeIs('share-links.*')]) href="{{ route('share-links.index') }}">@include('partials._nav-icon',['name'=>'invoices']) Customer Share Links</a>@endif
            @if($u->hasPermission('customers.view'))<a @class(['active'=>request()->routeIs('customers.*')]) href="{{ route('customers.index') }}">@include('partials._nav-icon',['name'=>'customers']) Customers</a>@endif
            <p>Operations</p>
            @if($u->hasPermission('deliveries.view'))<a @class(['active'=>request()->routeIs('deliveries.*')]) href="{{ route('deliveries.index') }}">@include('partials._nav-icon',['name'=>'deliveries']) Deliveries</a>@endif
            @if($u->hasPermission('products.view'))<a @class(['active'=>request()->routeIs('products.*')]) href="{{ route('products.index') }}">@include('partials._nav-icon',['name'=>'products']) Products</a>@endif
            @if($u->hasPermission('inventory.view'))<a @class(['active'=>request()->routeIs('inventory.*')]) href="{{ route('inventory.index') }}">@include('partials._nav-icon',['name'=>'inventory']) Inventory</a>@endif
            @if($u->hasPermission('purchases.view'))<a @class(['active'=>request()->routeIs('purchases.*','suppliers.*','raw-materials.*')]) href="{{ route('purchases.index') }}">@include('partials._nav-icon',['name'=>'purchases']) Purchases & Suppliers</a>@endif
            <p>Finance</p>
            @if($u->hasPermission('expenses.view'))<a @class(['active'=>request()->routeIs('expenses.*')]) href="{{ route('expenses.index') }}">@include('partials._nav-icon',['name'=>'expenses']) Expenses</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('accounting.*')]) href="{{ route('accounting.index') }}">@include('partials._nav-icon',['name'=>'accounting']) Accounting</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('finance.accounts')]) href="{{ route('finance.accounts') }}">@include('partials._nav-icon',['name'=>'accounting']) Bank & Cash</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('cashflow.index')]) href="{{ route('cashflow.index') }}">@include('partials._nav-icon',['name'=>'accounting']) Cashflow</a>@endif
            @if($u->hasPermission('expenses.view'))<a @class(['active'=>request()->routeIs('finance.budgets')]) href="{{ route('finance.budgets') }}">@include('partials._nav-icon',['name'=>'expenses']) Budgets</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('finance.cash-reconciliation')]) href="{{ route('finance.cash-reconciliation') }}">@include('partials._nav-icon',['name'=>'accounting']) Cash Reconciliation</a>@endif
            @if($u->hasPermission('accounting.view'))<a @class(['active'=>request()->routeIs('finance.bank-reconciliation*')]) href="{{ route('finance.bank-reconciliation') }}">@include('partials._nav-icon',['name'=>'accounting']) Bank Reconciliation</a>@endif
            @if($u->hasPermission('reports.financial'))<a @class(['active'=>request()->routeIs('vat.*')]) href="{{ route('vat.index') }}">@include('partials._nav-icon',['name'=>'accounting']) VAT Report</a>@endif
            @if($u->hasPermission('exports.view'))<a @class(['active'=>request()->routeIs('exports.*')]) href="{{ route('exports.index') }}">@include('partials._nav-icon',['name'=>'import']) Export Center</a>@endif
            @if($u->hasPermission('reports.view'))<a @class(['active'=>request()->routeIs('reports.*')]) href="{{ route('reports.index') }}">@include('partials._nav-icon',['name'=>'reports']) Reports</a>@endif
            @if($u->hasPermission('dashboard.view'))<a @class(['active'=>request()->routeIs('tasks.*')]) href="{{ route('tasks.index') }}">@include('partials._nav-icon',['name'=>'audit']) Tasks</a>@endif
            @if($u->hasPermission('dashboard.view'))<a @class(['active'=>request()->routeIs('calendar.*')]) href="{{ route('calendar.index') }}">@include('partials._nav-icon',['name'=>'deliveries']) Calendar</a>@endif
            @if($u->hasPermission('users.manage')||$u->hasPermission('settings.manage')||$u->hasPermission('system.view'))<p>Administration</p>@endif
            @if($u->hasPermission('users.manage'))<a @class(['active'=>request()->routeIs('users.*')]) href="{{ route('users.index') }}">@include('partials._nav-icon',['name'=>'roles']) Users & Roles</a>@endif
            @if($u->hasPermission('settings.manage'))<a @class(['active'=>request()->routeIs('settings.*')]) href="{{ route('settings.index') }}">@include('partials._nav-icon',['name'=>'settings']) Settings</a>@endif
            @if($u->hasPermission('audit.view'))<a @class(['active'=>request()->routeIs('system.audit')]) href="{{ route('system.audit') }}">@include('partials._nav-icon',['name'=>'audit']) Audit Log</a>@endif
            @if($u->hasPermission('system.view'))<a @class(['active'=>request()->routeIs('system.health','system.import-export','backups.*')]) href="{{ route('system.health') }}">@include('partials._nav-icon',['name'=>'system']) System</a>@endif
            @if($u->hasPermission('imports.manage'))<a @class(['active'=>request()->routeIs('imports.*')]) href="{{ route('imports.create') }}">@include('partials._nav-icon',['name'=>'import']) Import Customers/Orders</a>@endif
            @if($u->hasPermission('imports.manage'))<a @class(['active'=>request()->routeIs('products.import.*')]) href="{{ route('products.import.create') }}">@include('partials._nav-icon',['name'=>'import']) Import Products</a>@endif
        </nav>
        <div class="sidebar-user"><span class="sidebar-user-avatar">{{ collect(explode(' ',$u->name))->map(fn($p)=>mb_substr($p,0,1))->take(2)->join('') }}</span><span><b>{{ $u->name }}</b><small>{{ $u->roles->first()?->label ?? 'Team member' }}</small></span></div>
    </aside>
    <main class="main">
        <header class="topbar"><button class="menu" data-sidebar>☰</button><div><h1>@yield('title','Dashboard')</h1><small>@yield('subtitle','Ivory Gifts operations at a glance')</small></div><div class="combo global-search"><input type="text" data-global-search-input autocomplete="off" placeholder="Search orders, invoices, customers, products..."><div class="combo-results" data-global-search-results></div></div><div class="topbar-meta"><div class="live-clock" data-live-clock><strong data-live-time>{{ now('Asia/Dubai')->format('h:i:s A') }}</strong><small data-live-date>{{ now('Asia/Dubai')->format('D, d M Y') }} · UAE time</small></div><div class="user"><div class="notif-bell-wrap"><button type="button" class="theme-toggle" data-notif-toggle aria-label="Notifications" title="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>@if(count($notifications??[])>0)<span class="notif-badge">{{ count($notifications) }}</span>@endif</button><div class="notif-dropdown" data-notif-dropdown hidden>@forelse(($notifications??[]) as $n)<a href="{{ $n['url'] }}" class="notif-item"><span class="ai-insight-icon {{ $n['severity'] }}" style="width:26px;height:26px;font-size:11px">!</span><span><b>{{ $n['title'] }}</b><small>{{ $n['message'] }}</small></span></a>@empty<div class="notif-empty">No alerts — everything is on track.</div>@endforelse</div></div><button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle light/dark theme" title="Toggle light/dark theme"><svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.5M12 19v2.5M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2.5 12H5M19 12h2.5M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg></button><span>{{ $u->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="link">Sign out</button></form></div></div></header>
        <section class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if(session('info'))<div class="alert" style="border-color:var(--brown);color:var(--brown);background:rgba(34,211,238,.08)">{{ session('info') }}</div>@endif
            @if($errors->any())<div class="alert danger"><b>Please check the following:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
    @if($financialSummary)
    <footer class="finance-bar no-print">
        <div><small>Bank Balance</small><b>AED {{ number_format($financialSummary['bank_balance'],2) }}</b></div>
        <div><small>Cash in Hand</small><b>AED {{ number_format($financialSummary['cash_in_hand'],2) }}</b></div>
        <div><small>Inventory Value</small><b>AED {{ number_format($financialSummary['inventory_value'],2) }}</b></div>
        <div><small>Payables</small><b>AED {{ number_format($financialSummary['payables'],2) }}</b></div>
        <div><small>Receivables</small><b>AED {{ number_format($financialSummary['receivables'],2) }}</b></div>
    </footer>
    @endif
</div>
<nav class="mobile-nav"><a href="{{ route('dashboard') }}">Home</a>@if($u->hasPermission('orders.view'))<a href="{{ route('orders.index') }}">Orders</a>@endif @if($u->hasPermission('deliveries.view'))<a href="{{ route('deliveries.index') }}">Delivery</a>@endif<button data-sidebar>More</button></nav>
<dialog id="proof-viewer" class="quick-dialog"><div class="dialog-head"><h2>Proof</h2><button type="button" class="dialog-close" data-close-dialog>×</button></div><iframe data-proof-viewer-frame src="about:blank" style="width:100%;height:70vh;border:1px solid var(--line);border-radius:9px;background:#0a0e1a" title="Proof document"></iframe></dialog>
@include('partials._ready-whatsapp-js')
<script>window.IVORY_SYNC_URL=@json(route('sync.version'));(()=>{const c=document.querySelector('[data-live-clock]');if(!c)return;const t=c.querySelector('[data-live-time]'),d=c.querySelector('[data-live-date]'),tf=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Dubai',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}),df=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Dubai',weekday:'short',day:'2-digit',month:'short',year:'numeric'}),update=()=>{const n=new Date();t.textContent=tf.format(n);d.textContent=df.format(n)+' · UAE time'};update();setInterval(update,1000)})();</script><script src="{{ asset('build/assets/app.js') }}?v={{ @filemtime(public_path('build/assets/app.js')) ?: time() }}" defer></script>@stack('scripts')
</body></html>
