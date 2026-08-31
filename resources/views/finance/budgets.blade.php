@extends('layouts.app')
@section('title','Expense Budgets')
@section('subtitle','Monthly budgets by category')
@section('content')
<form class="toolbar"><input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"></form>

@if(auth()->user()->hasPermission('expenses.manage'))
<div class="card"><h2>Set budget</h2><form method="post" action="{{ route('finance.budgets.store') }}" style="margin-top:12px"><div class="form-grid">
<label>Category<input name="category" required placeholder="e.g. Rent"></label>
<label>Month<input type="month" name="month" value="{{ $month }}" required></label>
<label>Budget amount<input type="number" step="0.01" min="0" name="budget_amount" required></label>
</div><div class="actions"><button class="btn primary">Save budget</button></div></form></div>
@endif

<div class="card" style="margin-top:18px"><h2>Budget vs actual — {{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</h2>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Category</th><th>Budget</th><th>Actual</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
@forelse($rows as $r)
<tr><td><b>{{ $r['category'] }}</b></td><td>AED {{ number_format($r['budget'],2) }}</td><td>AED {{ number_format($r['actual'],2) }}</td><td class="{{ $r['remaining']>=0?'kpi-good':'kpi-bad' }}">AED {{ number_format($r['remaining'],2) }}</td><td>@if($r['budget']<=0)<span class="badge">No budget set</span>@elseif($r['over_budget'])<span class="badge red">Over budget</span>@else<span class="badge green">On track</span>@endif</td></tr>
@empty
<tr><td colspan="5" class="empty">No expenses or budgets for this month.</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
