@extends('layouts.app')
@section('title','Account Transfer')
@section('subtitle','Move money between Cash, Bank and Petty Cash accounts — never creates income, expense or affects profit')
@section('content')

@if(auth()->user()->hasPermission('accounting.manage'))
<div class="card">
<h2>New Transfer</h2>
<form method="post" action="{{ route('finance.account-transfer.store') }}" enctype="multipart/form-data" style="margin-top:12px" onsubmit="return ivoryCheckTransferAccounts(this)">
@csrf
<div class="form-grid">
<label>From Account<select name="from_account_id" required>
<option value="">Select...</option>
@foreach($accounts as $acc)<option value="{{ $acc->id }}" @selected(old('from_account_id')==$acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>@endforeach
</select></label>
<label>To Account<select name="to_account_id" required>
<option value="">Select...</option>
@foreach($accounts as $acc)<option value="{{ $acc->id }}" @selected(old('to_account_id')==$acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>@endforeach
</select></label>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></label>
<label>Date<input type="date" name="transfer_date" value="{{ old('transfer_date', today()->toDateString()) }}" required></label>
<label>Reference <span class="muted">(optional)</span><input name="reference" maxlength="190" placeholder="e.g. deposit slip no." value="{{ old('reference') }}"></label>
<label>Notes <span class="muted">(optional)</span><input name="notes" value="{{ old('notes') }}"></label>
<label class="span-2">Proof / Deposit Slip <span class="muted">(optional)</span><input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
</div>
<div class="actions"><button class="btn primary">Record Transfer</button></div>
</form>
</div>
<script>function ivoryCheckTransferAccounts(f){if(f.from_account_id.value&&f.from_account_id.value===f.to_account_id.value){alert('From and To account cannot be the same.');return false;}return true;}</script>
@else
<div class="card"><p class="muted">You have view-only access. Contact an admin to record a transfer.</p></div>
@endif

<div class="card" style="margin-top:18px"><h2>Recent Transfers</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>#</th><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Reference</th><th>Notes</th><th>Proof</th></tr></thead><tbody>
@forelse($transfers as $t)
<tr>
<td><b>{{ $t->transfer_number }}</b></td>
<td>{{ $t->transfer_date->format('d M Y') }}</td>
<td>{{ $t->fromAccount->name }}</td>
<td>{{ $t->toAccount->name }}</td>
<td class="amount">AED {{ number_format($t->amount,2) }}</td>
<td>{{ $t->reference ?: '—' }}</td>
<td>{{ $t->notes ?: '—' }}</td>
<td>@if($t->proof_path)<a class="btn small" href="{{ route('account-transfer.proof',$t) }}" target="_blank">View</a>@else—@endif</td>
</tr>
@empty
<tr><td colspan="8" class="empty">No transfers recorded yet.</td></tr>
@endforelse
</tbody></table></div></div>

@endsection
