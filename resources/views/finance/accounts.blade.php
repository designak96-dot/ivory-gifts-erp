@extends('layouts.app')
@section('title','Bank & Cash Accounts')
@section('subtitle','Track balances across every bank, cash, card and petty cash account')
@section('content')
<form class="toolbar"><input type="date" name="from" value="{{ $from }}"><input type="date" name="to" value="{{ $to }}"><button class="btn">Filter period</button>@if($from||$to)<a class="btn" href="{{ route('finance.accounts') }}">Clear</a>@endif</form>

@if(auth()->user()->hasPermission('accounting.manage'))
<div class="card"><h2>Add account</h2><form method="post" action="{{ route('finance.accounts.store') }}" style="margin-top:12px"><div class="form-grid">
<label>Name<input name="name" required placeholder="e.g. ADCB Current Account"></label>
<label>Type<select name="account_subtype">@foreach($subtypes as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<label>Opening balance<input type="number" step="0.01" name="opening_balance" value="0" required></label>
</div><div class="actions"><button class="btn primary">Add account</button></div></form></div>
@endif

<div class="grid cols-4" style="margin-top:18px">
@forelse($accounts as $row)
<div class="card"><div class="card-header"><h2>{{ $row['account']->name }}</h2><span class="badge blue">{{ $subtypes[$row['account']->account_subtype] ?? $row['account']->account_subtype }}</span></div>
<p class="muted" style="font-size:11px;text-transform:uppercase;margin-top:10px">Current balance</p>
<p class="{{ $row['current_balance']>=0?'kpi-good':'kpi-bad' }}" style="font-size:22px;font-weight:800">AED {{ number_format($row['current_balance'],2) }}</p>
<p class="muted" style="font-size:12px">Opening: AED {{ number_format($row['account']->opening_balance,2) }}</p>
<p style="font-size:12px"><span class="kpi-good">In: AED {{ number_format($row['money_in'],2) }}</span> · <span class="kpi-bad">Out: AED {{ number_format($row['money_out'],2) }}</span></p>
</div>
@empty
<p class="empty">No bank/cash accounts yet.</p>
@endforelse
</div>
@endsection
