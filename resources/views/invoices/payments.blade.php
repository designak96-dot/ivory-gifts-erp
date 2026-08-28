@extends('layouts.app') @section('title','Payments') @section('subtitle','Every payment posted, with proof — the linked invoice, method, and date at a glance')
@section('content')
<div class="toolbar"><form><input name="q" value="{{ request('q') }}" placeholder="Payment number or customer"><button class="btn">Filter</button></form><a class="btn" href="{{ route('invoices.index') }}">View invoices</a></div>
<div class="table-wrap mobile-cards"><table><thead><tr><th>Payment</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Date</th><th>Amount</th><th>Proof</th><th></th></tr></thead><tbody>
@forelse($payments as $p)
<tr>
<td data-label="Payment"><b>{{ $p->payment_number }}</b>{{ $p->reference_number?' · '.$p->reference_number:'' }}</td>
<td data-label="Customer">{{ $p->customer?->name??'—' }}</td>
<td data-label="Invoice">@forelse($p->allocations as $a)<a href="{{ route('invoices.show',$a->invoice) }}">{{ $a->invoice->invoice_number }}</a>@if(!$loop->last), @endif@empty<span class="muted">Unallocated</span>@endforelse</td>
<td data-label="Method">{{ str_replace('_',' ',$p->method) }}</td>
<td data-label="Date">{{ $p->payment_date->format('d M Y') }}</td>
<td data-label="Amount" class="amount">AED {{ number_format($p->amount,2) }}</td>
<td data-label="Proof">@if($p->proof_path)<button type="button" class="btn small" data-view-proof="{{ route('payments.proof',$p) }}">View proof</button>@else<span class="muted">— (historical)</span>@endif</td>
<td data-label="Actions">@if(auth()->user()->hasPermission('payments.delete'))<form method="post" action="{{ route('payments.destroy',$p) }}">@csrf @method('delete')<button type="submit" class="btn-link danger" data-confirm="Are you sure you want to delete this Payment? Invoice balances will be recalculated.">Delete</button></form>@endif</td>
</tr>
@empty
<tr><td colspan="8" class="empty">No payments posted yet.</td></tr>
@endforelse
</tbody></table></div>{{ $payments->links() }}
@endsection
