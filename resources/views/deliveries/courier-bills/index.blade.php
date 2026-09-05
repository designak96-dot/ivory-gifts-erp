@extends('layouts.app')
@section('title','Courier Bills')
@section('content')
@if(auth()->user()->hasPermission('courier-bills.manage'))
<div class="toolbar"><a class="btn primary" href="{{ route('courier-bills.create') }}">+ New Courier Bill</a></div>
@endif
<div class="card" style="margin-top:12px"><div class="table-wrap"><table><thead><tr><th>Bill #</th><th>Courier</th><th>Period</th><th>Deliveries</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead><tbody>
@forelse($bills as $b)
<tr>
<td><a href="{{ route('courier-bills.show',$b) }}"><b>{{ $b->bill_number }}</b></a></td>
<td>{{ $b->supplier->name }}</td>
<td>{{ $b->period_start->format('d M') }} – {{ $b->period_end->format('d M Y') }}</td>
<td>{{ $b->lines->count() }}</td>
<td class="amount"><b>{{ $b->currency }} {{ number_format($b->total_amount,2) }}</b></td>
<td class="amount kpi-good">AED {{ number_format($b->amount_paid,2) }}</td>
<td><span class="badge {{ $b->status==='paid'?'green':($b->status==='partially_paid'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$b->status)) }}</span></td>
</tr>
@empty<tr><td colspan="7" class="empty">No courier bills yet.</td></tr>@endforelse
</tbody></table></div>{{ $bills->links() }}</div>
@endsection
