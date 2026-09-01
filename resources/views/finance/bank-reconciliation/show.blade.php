@extends('layouts.app')
@section('title','Bank Reconciliation — '.$reconciliation->statement_month->format('F Y'))
@section('subtitle',$reconciliation->bankAccount->name)
@section('content')

<div class="card">
<h2>Reconciliation Summary</h2>
<div class="grid cols-4" style="margin-top:15px">
<div class="stat"><small>Statement Month</small><strong>{{ $reconciliation->statement_month->format('F Y') }}</strong></div>
<div class="stat"><small>Opening Balance</small><strong>{{ $reconciliation->opening_balance!==null?'AED '.number_format($reconciliation->opening_balance,2):'—' }}</strong></div>
<div class="stat"><small>Closing Balance</small><strong>{{ $reconciliation->closing_balance!==null?'AED '.number_format($reconciliation->closing_balance,2):'—' }}</strong></div>
<div class="stat"><small>Status</small><strong><span class="badge {{ $reconciliation->status==='reconciled'?'green':'amber' }}">{{ $reconciliation->status==='reconciled'?'Reconciled':'Needs Review' }}</span></strong></div>
</div>
<div class="grid cols-4" style="margin-top:12px">
<div class="stat"><small>Total Credits</small><strong class="kpi-good">AED {{ number_format($reconciliation->total_credits,2) }}</strong></div>
<div class="stat"><small>Total Debits</small><strong class="kpi-bad">AED {{ number_format($reconciliation->total_debits,2) }}</strong></div>
<div class="stat"><small>Matched</small><strong>{{ $reconciliation->matched_count }}</strong></div>
<div class="stat"><small>Missing Transaction Count</small><strong class="{{ $reconciliation->missing_count>0?'kpi-bad':'' }}">{{ $reconciliation->missing_count }}</strong></div>
</div>
<div class="grid cols-4" style="margin-top:12px">
<div class="stat"><small>Unmatched Money In</small><strong class="{{ $reconciliation->unmatched_in_count>0?'kpi-bad':'' }}">{{ $reconciliation->unmatched_in_count }}</strong></div>
<div class="stat"><small>Unmatched Money Out</small><strong class="{{ $reconciliation->unmatched_out_count>0?'kpi-bad':'' }}">{{ $reconciliation->unmatched_out_count }}</strong></div>
@if($reconciliation->statement_file_path)<div class="stat"><small>Original Statement</small><a class="btn small" href="{{ route('finance.bank-reconciliation.statement',$reconciliation) }}">Download</a></div>@endif
</div>
</div>

<div class="card" style="margin-top:18px"><h2>Statement Transactions</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Status</th><th>Matched To</th><th>Actions</th></tr></thead><tbody>
@foreach($reconciliation->transactions as $txn)
<tr>
<td>{{ $txn->txn_date->format('d M Y') }}</td>
<td>{{ $txn->description?:'—' }}</td>
<td>{{ $txn->bank_reference?:'—' }}</td>
<td class="amount">{{ $txn->debit>0?number_format($txn->debit,2):'' }}</td>
<td class="amount">{{ $txn->credit>0?number_format($txn->credit,2):'' }}</td>
<td>
@if($txn->match_status==='matched')<span class="badge green">✓ Matched</span>
@elseif($txn->match_status==='possible_match')<span class="badge amber">⚠ Possible Match</span>
@else<span class="badge red">✕ Missing in ERP</span>
@endif
</td>
<td>@if($txn->matched_type)<span class="muted">{{ ucwords(str_replace('_',' ',$txn->matched_type)) }} #{{ $txn->matched_id }}</span>@else—@endif</td>
<td>
@if($txn->match_status==='missing_in_erp' && auth()->user()->hasPermission('accounting.manage'))
@if($txn->amount > 0)
<details><summary class="btn small">Money Received</summary>
<form method="post" action="{{ route('bank-txn.create-payment',$txn) }}" style="margin-top:6px;display:flex;gap:4px">@csrf<select name="customer_id" required><option value="">Customer...</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select><button class="btn small primary">Create Payment</button></form>
</details>
@else
<details><summary class="btn small">Money Spent</summary>
<form method="post" action="{{ route('bank-txn.create-expense',$txn) }}" style="margin-top:6px;display:flex;gap:4px">@csrf<input name="category" placeholder="Category" required style="width:100px"><input name="payee" placeholder="Payee"><button class="btn small primary">Create Expense</button></form>
</details>
@endif
@endif
</td>
</tr>
@endforeach
</tbody></table></div></div>

@if($missingFromStatement['payments']->count() || $missingFromStatement['expenses']->count() || $missingFromStatement['raw_material_purchases']->count() || $missingFromStatement['account_transfers']->count())
<div class="card" style="margin-top:18px"><h2>⚠ ERP Transactions Missing From Bank Statement</h2>
<div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Type</th><th>Number</th><th>Date</th><th>Amount</th></tr></thead><tbody>
@foreach($missingFromStatement['payments'] as $p)<tr><td>Payment</td><td>{{ $p->payment_number }}</td><td>{{ $p->payment_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($p->amount,2) }}</td></tr>@endforeach
@foreach($missingFromStatement['expenses'] as $e)<tr><td>Expense</td><td>{{ $e->expense_number }}</td><td>{{ $e->expense_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($e->total_amount,2) }}</td></tr>@endforeach
@foreach($missingFromStatement['raw_material_purchases'] as $rmp)<tr><td>Raw Material Purchase</td><td>{{ $rmp->purchase_number }}</td><td>{{ $rmp->purchase_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($rmp->total_amount,2) }}</td></tr>@endforeach
@foreach($missingFromStatement['account_transfers'] as $t)<tr><td>Account Transfer</td><td>{{ $t->transfer_number }}</td><td>{{ $t->transfer_date->format('d M Y') }}</td><td class="amount">AED {{ number_format($t->amount,2) }}</td></tr>@endforeach
</tbody></table></div></div>
@endif

@endsection
