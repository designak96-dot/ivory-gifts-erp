@extends('layouts.app')
@section('title','Cash Reconciliation')
@section('subtitle','Compare expected cash against a physical count, and record approved cash adjustments')
@section('content')

<div class="card">
<h2>Reconciliation</h2>
<form method="get" class="form-grid" style="margin-top:15px">
<label>Cash Account<select name="cash_account_id" onchange="this.form.submit()">
@foreach($cashAccounts as $acc)<option value="{{ $acc->id }}" @selected($selectedAccount && $selectedAccount->id===$acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>@endforeach
</select></label>
<label>Reconciliation Date<input type="date" name="reconciliation_date" value="{{ $reconciliationDate }}" onchange="this.form.submit()"></label>
</form>

@if($preview)
<div class="grid cols-4" style="margin-top:18px">
<div class="stat"><small>Opening Cash</small><strong>AED {{ number_format($preview['opening_cash'],2) }}</strong></div>
<div class="stat"><small>Cash In</small><strong class="kpi-good">+ AED {{ number_format($preview['cash_in'],2) }}</strong></div>
<div class="stat"><small>Cash Out</small><strong class="kpi-bad">− AED {{ number_format($preview['cash_out'],2) }}</strong></div>
<div class="stat"><small>Expected Cash Balance</small><strong>AED {{ number_format($preview['expected_cash'],2) }}</strong></div>
</div>

@if(auth()->user()->hasPermission('accounting.manage'))
<form method="post" action="{{ route('finance.cash-reconciliation.store') }}" style="margin-top:18px">
@csrf
<input type="hidden" name="cash_account_id" value="{{ $selectedAccount->id }}">
<input type="hidden" name="reconciliation_date" value="{{ $reconciliationDate }}">
<div class="form-grid">
<label>Actual Physical Cash Count<input type="number" step="0.01" min="0" name="physical_cash_count" placeholder="Count the drawer/safe, enter here"></label>
<label>Notes<input name="notes" placeholder="Optional"></label>
</div>
<div class="actions"><button class="btn primary">Save Reconciliation</button></div>
</form>
@endif
@else
<p class="muted" style="margin-top:15px">No cash or petty cash accounts are set up yet.</p>
@endif
</div>

@if(auth()->user()->hasPermission('accounting.manage'))
<div class="card" style="margin-top:18px"><h2>Record Adjustment</h2>
<form method="post" action="{{ route('finance.cash-reconciliation.adjustment') }}" enctype="multipart/form-data">
@csrf
<div class="form-grid" style="margin-top:15px">
<label>Cash Account<select name="cash_account_id" required>@foreach($cashAccounts as $acc)<option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>@endforeach</select></label>
<label>Type<select name="type" required>@foreach(\App\Models\CashAdjustment::TYPES as $val=>$label)<option value="{{ $val }}">{{ $label }}</option>@endforeach</select></label>
<label>Cash In / Cash Out<select name="direction" required><option value="in">Cash In (Receipt)</option><option value="out">Cash Out (Payment)</option></select></label>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
<label>Date<input type="date" name="adjustment_date" value="{{ today()->toDateString() }}" required></label>
<label>Reason<input name="reason" required maxlength="255"></label>
<label class="span-2">Proof <span class="muted">(optional)</span><input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
</div>
<div class="actions"><button class="btn primary">Record Adjustment</button></div>
</form>
</div>
@endif

<div class="grid cols-2" style="margin-top:18px">
<div class="card"><h2>Reconciliation History</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Date</th><th>Account</th><th>Expected</th><th>Physical</th><th>Difference</th></tr></thead><tbody>
@forelse($history as $h)
<tr><td>{{ $h->reconciliation_date->format('d M Y') }}</td><td>{{ $h->cashAccount->name }}</td><td class="amount">AED {{ number_format($h->expected_cash,2) }}</td><td class="amount">{{ $h->physical_cash_count!==null?'AED '.number_format($h->physical_cash_count,2):'—' }}</td><td>
@if($h->hasDifference())<span class="badge red">AED {{ number_format($h->difference,2) }} — Cash difference requires review.</span>
@elseif($h->physical_cash_count!==null)<span class="badge green">Matched</span>
@else<span class="muted">—</span>@endif
</td></tr>
@empty
<tr><td colspan="5" class="empty">No reconciliations recorded yet.</td></tr>
@endforelse
</tbody></table></div></div>

<div class="card"><h2>Recent Adjustments</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Reference</th><th>Type</th><th>Direction</th><th>Amount</th><th>Reason</th></tr></thead><tbody>
@forelse($recentAdjustments as $adj)
<tr><td><b>{{ $adj->reference }}</b></td><td>{{ \App\Models\CashAdjustment::TYPES[$adj->type] ?? $adj->type }}</td><td><span class="badge {{ $adj->direction==='in'?'green':'amber' }}">{{ $adj->direction==='in'?'Cash In':'Cash Out' }}</span></td><td class="amount">AED {{ number_format($adj->amount,2) }}</td><td>{{ $adj->reason }}</td></tr>
@empty
<tr><td colspan="5" class="empty">No adjustments recorded yet.</td></tr>
@endforelse
</tbody></table></div></div>
</div>

@endsection
