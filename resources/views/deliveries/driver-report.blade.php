@extends('layouts.app')
@section('title','Driver Report — '.$driver->name)
@section('content')

<div class="toolbar no-print">
<button class="btn" onclick="window.print()">Print / Download PDF</button>
@if(auth()->user()->hasPermission('driver-settlements.manage'))
<form method="post" action="{{ route('driver-settlements.store') }}" style="display:inline">
@csrf
<input type="hidden" name="driver_id" value="{{ $driver->id }}">
<input type="hidden" name="start_date" value="{{ $data['start_date'] }}">
<input type="hidden" name="end_date" value="{{ $data['end_date'] }}">
<button class="btn primary">Approve Settlement</button>
</form>
@endif
</div>

<div class="card report-print-area" id="driver-report">
<div style="display:flex;justify-content:space-between;align-items:start;border-bottom:2px solid var(--border);padding-bottom:15px">
<div>
<h1 style="margin:0">{{ $settings['company_legal_name'] ?? $settings['company_name'] ?? 'Ivory Gifts' }}</h1>
<p class="muted">{{ $settings['company_address'] ?? '' }}</p>
@if(!empty($settings['company_trn']))<p class="muted">TRN: {{ $settings['company_trn'] }}</p>@endif
</div>
<div style="text-align:right">
<h2 style="margin:0">Driver Daily Report</h2>
@if($report['remaining_payable'] <= 0 && $report['already_paid'] > 0)
<span class="badge green" style="font-size:1.1em">PAID</span>
@else
<span class="badge red" style="font-size:1.1em">DRAFT — UNPAID</span>
@endif
</div>
</div>

<div class="form-grid" style="margin-top:15px">
<div><b>Driver:</b> {{ $driver->name }}</div>
<div><b>Period:</b> {{ \Carbon\Carbon::parse($data['start_date'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($data['end_date'])->format('d M Y') }}</div>
<div><b>Generated:</b> {{ now()->format('d M Y H:i') }}</div>
</div>

<h3 style="margin-top:20px">Deliveries ({{ count($report['lines']) }})</h3>
<div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Order</th><th>Customer / Area</th><th>Completed</th><th>Charge</th><th>Collected</th><th>Fee</th><th>Allowance</th><th>Petrol</th><th>Profit/Loss</th></tr></thead><tbody>
@forelse($report['lines'] as $line)
<tr>
<td>{{ $line['order_number'] }}</td>
<td>{{ $line['customer'] }}@if($line['area']) / {{ $line['area'] }}@endif</td>
<td>{{ $line['completed_at']?->format('d M H:i') }}</td>
<td class="amount">AED {{ number_format($line['delivery_charge'],2) }}</td>
<td class="amount">AED {{ number_format($line['amount_collected'],2) }}</td>
<td class="amount">AED {{ number_format($line['driver_fee'],2) }}</td>
<td class="amount">AED {{ number_format($line['allocated_allowance'],2) }}</td>
<td class="amount">AED {{ number_format($line['allocated_petrol'],2) }}</td>
<td class="amount {{ $line['profit_loss']>=0?'kpi-good':'kpi-bad' }}">AED {{ number_format($line['profit_loss'],2) }}</td>
</tr>
@empty<tr><td colspan="9" class="empty">No completed deliveries in this period.</td></tr>
@endforelse
</tbody></table></div>

<div class="grid cols-2" style="margin-top:20px;gap:20px">
<div class="card" style="background:transparent">
<h3>Company Delivery Result</h3>
<table style="width:100%">
<tr><td>Delivery revenue</td><td class="amount">AED {{ number_format($report['revenue'],2) }}</td></tr>
<tr><td>Uncollected delivery charges</td><td class="amount kpi-bad">AED {{ number_format($report['uncollected'],2) }}</td></tr>
<tr><td>Driver delivery fees</td><td class="amount">− AED {{ number_format($report['fee_total'],2) }}</td></tr>
<tr><td>Daily mobile allowance</td><td class="amount">− AED {{ number_format($report['allowance_total'],2) }}</td></tr>
<tr><td>Petrol (allocated)</td><td class="amount">− AED {{ number_format($report['petrol_total'],2) }}</td></tr>
<tr style="border-top:1px solid var(--border)"><td><b>Daily delivery profit/loss</b></td><td class="amount {{ $report['daily_profit_loss']>=0?'kpi-good':'kpi-bad' }}"><b>AED {{ number_format($report['daily_profit_loss'],2) }}</b></td></tr>
</table>
</div>
<div class="card" style="background:transparent">
<h3>Amount Payable to Driver</h3>
<p class="muted" style="font-size:0.9em">This is not the same figure as company profit — the driver earns a fixed fee and allowance regardless of delivery profitability.</p>
<table style="width:100%">
<tr><td>Driver delivery fees</td><td class="amount">AED {{ number_format($report['fee_total'],2) }}</td></tr>
<tr><td>Daily mobile allowance</td><td class="amount">AED {{ number_format($report['allowance_total'],2) }}</td></tr>
<tr><td>Approved unpaid reimbursements</td><td class="amount">AED {{ number_format($report['reimbursements_due'],2) }}</td></tr>
<tr style="border-top:1px solid var(--border)"><td><b>Total driver entitlement</b></td><td class="amount"><b>AED {{ number_format($report['entitlement'],2) }}</b></td></tr>
<tr><td>Previous settlement payments</td><td class="amount">− AED {{ number_format($report['already_paid'],2) }}</td></tr>
<tr style="border-top:2px solid var(--border)"><td><b>Remaining payable</b></td><td class="amount kpi-bad"><b>AED {{ number_format($report['remaining_payable'],2) }}</b></td></tr>
</table>
</div>
</div>

<div style="display:flex;justify-content:space-between;margin-top:60px">
<div style="text-align:center"><div style="border-top:1px solid #888;width:200px;padding-top:6px">Driver Signature</div></div>
<div style="text-align:center"><div style="border-top:1px solid #888;width:200px;padding-top:6px">Manager Signature</div></div>
</div>
</div>

<style>
@media print {
  .no-print, nav, aside, header, .sidebar { display: none !important; }
  .report-print-area { border: none !important; box-shadow: none !important; }
  body { background: white !important; color: black !important; }
}
</style>
@endsection
