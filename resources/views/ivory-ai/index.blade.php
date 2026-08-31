@extends('layouts.app')
@section('title','Ivory AI')
@section('subtitle','Rule-based insights from your own ERP data — no external AI, no paid API')
@section('content')

<div class="card"><div class="card-header"><h2>Business Health</h2><span class="badge {{ $health['label']==='Healthy'?'green':($health['label']==='At risk'?'red':'amber') }}">{{ $health['label'] }} — {{ $health['score'] }}%</span></div>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Factor</th><th>Score</th><th>Weight</th><th>Detail</th></tr></thead><tbody>
@foreach($health['factors'] as $f)
<tr><td><b>{{ $f['name'] }}</b></td><td class="{{ $f['score']>=80?'kpi-good':($f['score']<50?'kpi-bad':'') }}">{{ $f['score'] }}%</td><td>{{ $f['weight']*100 }}%</td><td class="muted">{{ $f['detail'] }}</td></tr>
@endforeach
</tbody></table></div>
@if(count($health['weaknesses']))<p style="margin-top:12px"><b class="kpi-bad">Dragging the score down:</b> {{ collect($health['weaknesses'])->pluck('name')->join(', ') }}</p>@endif
@if(count($health['strengths']))<p><b class="kpi-good">Strong areas:</b> {{ collect($health['strengths'])->pluck('name')->join(', ') }}</p>@endif
</div>

<div class="card" style="margin-top:18px"><h2>Ivory AI Quick Actions</h2>
<div class="grid cols-4" style="margin-top:15px">
@foreach(['overdue-orders'=>'Overdue Orders','unpaid-balances'=>'Unpaid Balances','top-customers-month'=>'Top Customers This Month','orders-at-risk'=>'Orders at Risk','low-stock'=>'Low Stock','slow-stock'=>'Slow Stock','highest-profit-products'=>'Highest Profit Products','customers-to-follow-up'=>'Customers to Follow Up'] as $key=>$label)
<button type="button" class="btn" data-ai-quick-action="{{ $key }}">{{ $label }}</button>
@endforeach
</div>
<div data-ai-quick-action-results style="margin-top:15px"></div>
</div>

<div class="grid cols-2" style="margin-top:18px">
<div class="card"><h2>Orders at Risk</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Order</th><th>Risk</th><th>Days left</th><th>Stage</th></tr></thead><tbody>
@forelse($orderRisks->filter(fn($r)=>$r['level']!=='Low')->take(15) as $r)
<tr><td><a href="{{ route('orders.show',$r['order']) }}">{{ $r['order']->order_number }}</a></td><td><span class="badge {{ $r['level']==='High'?'red':'amber' }}">{{ $r['level'] }}</span></td><td>{{ $r['days_remaining'] }}</td><td>{{ $r['readiness_stage'] }}</td></tr>
@empty
<tr><td colspan="4" class="empty">No orders currently at risk.</td></tr>
@endforelse
</tbody></table></div></div>

<div class="card"><h2>Payment Risk</h2><p class="muted" style="font-size:11px">Informational only — never an automatic blacklist</p><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Customer</th><th>Risk</th><th>Overdue</th><th>Outstanding</th></tr></thead><tbody>
@forelse($paymentRisks->take(15) as $r)
<tr><td><a href="{{ route('customers.show',$r['customer']) }}">{{ $r['customer']->name }}</a></td><td><span class="badge {{ $r['level']==='High'?'red':'amber' }}">{{ $r['level'] }}</span></td><td>{{ $r['overdue_invoices'] }}</td><td class="amount">AED {{ number_format($r['outstanding_amount'],2) }}</td></tr>
@empty
<tr><td colspan="4" class="empty">No payment risk detected.</td></tr>
@endforelse
</tbody></table></div></div>
</div>

<div class="grid cols-2" style="margin-top:18px">
<div class="card"><h2>Sales Intelligence</h2>
@if($salesAnomaly['has_anomaly'] ?? false)
<div class="alert {{ $salesAnomaly['direction']==='drop'?'danger':'success' }}">Meaningful sales {{ $salesAnomaly['direction'] }} detected this week — AED {{ number_format($salesAnomaly['this_week'],2) }} vs an average of AED {{ number_format($salesAnomaly['average'],2) }} ({{ $salesAnomaly['deviations'] }} standard deviations).</div>
@else
<p class="muted">This week's sales (AED {{ number_format($salesAnomaly['this_week']??0,2) }}) are within normal range of the recent average (AED {{ number_format($salesAnomaly['average']??0,2) }}).</p>
@endif
</div>

<div class="card"><h2>Expense Intelligence</h2>
@forelse($expenseSpikes as $spike)
<div class="alert danger" style="margin-bottom:8px">{{ $spike['category'] }} up {{ $spike['change_percent'] }}% vs last month (AED {{ number_format($spike['previous'],2) }} → AED {{ number_format($spike['current'],2) }})</div>
@empty
<p class="muted">No unusual expense increases detected.</p>
@endforelse
</div>
</div>

<div class="card" style="margin-top:18px"><h2>Inventory Intelligence</h2>
<p class="muted" style="font-size:11px">Demand estimates from historical averages only — clearly labeled, never a guarantee</p>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Product</th><th>Monthly avg sold</th><th>Est. next month demand</th><th>Current stock</th><th>Recommended reorder</th></tr></thead><tbody>
@forelse($demandPredictions->take(10) as $p)
<tr><td><b>{{ $p['product']->name_en }}</b>@if($p['is_fast_moving'])<span class="badge blue" style="margin-left:6px">Fast-moving</span>@endif</td><td>{{ $p['monthly_average_sold'] }}</td><td>~{{ $p['estimated_next_month_demand'] }} (estimate)</td><td>{{ $p['current_stock'] }}</td><td>{{ $p['recommended_reorder_qty'] }}</td></tr>
@empty
<tr><td colspan="5" class="empty">Not enough sales history yet to estimate demand.</td></tr>
@endforelse
</tbody></table></div>
@if($slowMoving->count())
<p style="margin-top:12px"><b>Slow-moving (no sales in 60 days):</b> {{ $slowMoving->pluck('product.name_en')->join(', ') }}</p>
@endif
</div>

<div class="card" style="margin-top:18px"><h2>Recommendations</h2>
<div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
@forelse($recommendations as $rec)
<a href="{{ $rec['url'] }}" class="health" style="text-decoration:none"><span class="dot warning"></span><div><b>{{ $rec['action'] }}</b><div class="muted">{{ $rec['detail'] }}</div></div></a>
@empty
<p class="muted">No urgent recommendations right now.</p>
@endforelse
</div>
</div>

@if($repeatOpportunities->count())
<div class="card" style="margin-top:18px"><h2>Repeat Customer Opportunities</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Customer</th><th>Days since last order</th><th>Usual gap</th><th>Total orders</th></tr></thead><tbody>
@foreach($repeatOpportunities->take(10) as $r)
<tr><td><a href="{{ route('customers.show',$r['customer']) }}">{{ $r['customer']->name }}</a></td><td>{{ $r['days_since_last_order'] }} days</td><td>{{ $r['average_order_gap_days'] }} days</td><td>{{ $r['order_count'] }}</td></tr>
@endforeach
</tbody></table></div></div>
@endif

@endsection
@push('scripts')<script>
document.querySelectorAll('[data-ai-quick-action]').forEach(function(btn){
  btn.addEventListener('click', async function(){
    const box = document.querySelector('[data-ai-quick-action-results]');
    box.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const res = await fetch('{{ route("ivory-ai.quick-action") }}?action=' + encodeURIComponent(btn.dataset.aiQuickAction), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      if (!data.items || !data.items.length) { box.innerHTML = '<p class="muted">No results.</p>'; return; }
      box.innerHTML = '<div class="table-wrap"><table><tbody>' + data.items.map(function(i){
        return '<tr><td><a href="' + i.url + '">' + i.label + '</a></td><td class="muted">' + i.sub + '</td></tr>';
      }).join('') + '</table></div>';
    } catch (e) { box.innerHTML = '<p class="muted">Could not load results.</p>'; }
  });
});
</script>@endpush
