{{--
  Ivory AI panel — not a call to any AI model or external API. Every
  number here comes from IvoryAiInsightsService, a deterministic
  rule-based scoring engine over this ERP's own real database. No
  fabricated "confidence" percentages or invented figures.
--}}
@php
    $circumference = 2 * M_PI * 42;
    $offset = $circumference * (1 - $ivoryAi['health_score'] / 100);
@endphp
<div class="card ai-panel">
    <div class="ai-panel-head"><h2>Ivory AI <span class="ai-live-dot"></span> <span class="ai-live-label">LIVE</span></h2></div>
    <p class="muted ai-panel-sub">Real-time insights from your ERP data</p>
@if($ivoryAi['trend_confidence'] !== null)
    <p class="muted" style="font-size:11px;margin-top:-8px;margin-bottom:8px">Sales trend confidence: {{ $ivoryAi['trend_confidence'] }}%</p>
@endif
    <div class="ai-ring-wrap">
        <svg viewBox="0 0 100 100" class="ai-ring">
            <defs><linearGradient id="ai-ring-gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee"/><stop offset="100%" stop-color="#8b5cf6"/></linearGradient></defs>
            <circle cx="50" cy="50" r="42" class="ai-ring-track"/>
            <circle cx="50" cy="50" r="42" class="ai-ring-value" style="stroke-dasharray:{{ $circumference }};stroke-dashoffset:{{ $offset }}"/>
        </svg>
        <div class="ai-ring-text"><strong>{{ $ivoryAi['health_score'] }}<span>%</span></strong><small>{{ $ivoryAi['health_label'] }}</small></div>
    </div>

    <div class="ai-insights">
        @if($ivoryAi['orders_needing_attention']['count'] > 0)
        <a href="{{ route('orders.index') }}" class="ai-insight-row"><span class="ai-insight-icon amber">!</span><span><b>Orders needing attention</b><small>{{ $ivoryAi['orders_needing_attention']['count'] }} {{ $ivoryAi['orders_needing_attention']['count']===1?'order requires':'orders require' }} action</small></span></a>
        @endif
        @if($ivoryAi['outstanding']['count'] > 0)
        <a href="{{ route('invoices.index') }}" class="ai-insight-row"><span class="ai-insight-icon blue">$</span><span><b>Outstanding payments</b><small>AED {{ number_format($ivoryAi['outstanding']['amount'],2) }} across {{ $ivoryAi['outstanding']['count'] }} {{ $ivoryAi['outstanding']['count']===1?'invoice':'invoices' }}</small></span></a>
        @endif
        @if($ivoryAi['deliveries_at_risk']['count'] > 0)
        <a href="{{ route('deliveries.index') }}" class="ai-insight-row"><span class="ai-insight-icon red">!</span><span><b>Deliveries at risk</b><small>{{ $ivoryAi['deliveries_at_risk']['count'] }} {{ $ivoryAi['deliveries_at_risk']['count']===1?'delivery':'deliveries' }} may be delayed</small></span></a>
        @endif
        @if($ivoryAi['low_stock'] > 0)
        <a href="{{ route('inventory.index') }}" class="ai-insight-row"><span class="ai-insight-icon amber">▾</span><span><b>Stock running low</b><small>{{ $ivoryAi['low_stock'] }} {{ $ivoryAi['low_stock']===1?'product':'products' }} below reorder level</small></span></a>
        @endif
        @if($ivoryAi['expense_warning'])
        <div class="ai-insight-row"><span class="ai-insight-icon red">▲</span><span><b>Expense warning</b><small>Up {{ $ivoryAi['expense_warning']['change_percent'] }}% vs last month</small></span></div>
        @endif
        @if($ivoryAi['orders_needing_attention']['count']===0 && $ivoryAi['outstanding']['count']===0 && $ivoryAi['deliveries_at_risk']['count']===0 && $ivoryAi['low_stock']===0)
        <div class="ai-insight-row"><span class="ai-insight-icon green">✓</span><span><b>All clear</b><small>No urgent items right now</small></span></div>
        @endif
    </div>

    @if($ivoryAi['top_customers']->count())
    <p class="ai-section-label">Top customers</p>
    <div class="ai-mini-list">
        @foreach($ivoryAi['top_customers'] as $c)
        <div class="ai-mini-row"><span>{{ $c->name }}</span><span class="muted">AED {{ number_format($c->orders_sum_grand_total ?? 0,0) }}</span></div>
        @endforeach
    </div>
    @endif

    @if(count($ivoryAi['top_five_today']))
    <p class="ai-section-label">Top 5 today</p>
    <div class="ai-mini-list">
        @foreach($ivoryAi['top_five_today'] as $item)
        <div class="ai-mini-row"><span class="ai-insight-icon {{ $item['severity'] }}" style="width:20px;height:20px;font-size:10px">{{ $loop->iteration }}</span><span style="flex:1;font-size:12px">{{ $item['label'] }}</span></div>
        @endforeach
    </div>
    @endif

    <p class="ai-section-label">Next best action</p>
    <div class="ai-next-action"><span class="ai-next-action-icon">⚡</span><p>{{ $ivoryAi['next_best_action'] }}</p></div>
    <a href="{{ route('ivory-ai.index') }}" class="btn small" style="margin-top:14px;width:100%;text-align:center">View full Ivory AI report</a>
</div>
