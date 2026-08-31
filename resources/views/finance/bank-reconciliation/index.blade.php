@extends('layouts.app')
@section('title','Bank Reconciliation')
@section('subtitle','Upload a monthly bank statement and match it against real ERP records')
@section('content')

@if(auth()->user()->hasPermission('accounting.manage'))
<div class="card">
<h2>Upload Statement</h2>
<form method="post" action="{{ route('finance.bank-reconciliation.store') }}" enctype="multipart/form-data" style="margin-top:15px">
@csrf
<div class="form-grid">
<label>Bank Account<select name="bank_account_id" required>@foreach($bankAccounts as $acc)<option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>@endforeach</select></label>
<label>Statement Month<input type="month" name="statement_month" value="{{ now()->format('Y-m') }}" required></label>
<label>Opening Balance <span class="muted">(optional)</span><input type="number" step="0.01" name="opening_balance"></label>
<label>Closing Balance <span class="muted">(optional)</span><input type="number" step="0.01" name="closing_balance"></label>
<label class="span-2">Statement File — CSV, XLSX preferred (PDF accepted but not auto-parsed)<input type="file" name="statement_file" accept=".csv,.xlsx,.xls,.pdf" required></label>
</div>
<div class="actions"><button class="btn primary">Upload & Match</button></div>
</form>
</div>
@endif

<div class="card" style="margin-top:18px"><h2>Reconciliation History</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Statement Month</th><th>Bank Account</th><th>Transactions</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($history as $h)
<tr><td>{{ $h->statement_month->format('F Y') }}</td><td>{{ $h->bankAccount->name }}</td><td>{{ $h->transactions_count }}</td><td><span class="badge {{ $h->status==='reconciled'?'green':'amber' }}">{{ $h->status==='reconciled'?'Reconciled':'Needs Review' }}</span></td><td><a class="btn small" href="{{ route('finance.bank-reconciliation.show',$h) }}">Open</a></td></tr>
@empty
<tr><td colspan="5" class="empty">No bank reconciliations uploaded yet.</td></tr>
@endforelse
</tbody></table></div></div>

@endsection
