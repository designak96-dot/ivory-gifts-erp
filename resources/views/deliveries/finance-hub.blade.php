@extends('layouts.app')
@section('title','Delivery Finance')
@section('subtitle','Courier bills, driver settlements, vehicle expenses, drivers & settings — all in one place')
@section('content')

<div class="card">
<div style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:10px" id="finance-tabs">
<button class="btn small tab-btn active" data-tab="courier-bills">Courier Bills</button>
<button class="btn small tab-btn" data-tab="settlements">Driver Settlements</button>
<button class="btn small tab-btn" data-tab="vehicle-expenses">Vehicle Expenses</button>
<button class="btn small tab-btn" data-tab="drivers">Drivers & Vehicles</button>
<button class="btn small tab-btn" data-tab="settings">Settings</button>
</div>

{{-- ================= COURIER BILLS ================= --}}
<div class="tab-pane" data-pane="courier-bills" style="margin-top:15px">
@if(auth()->user()->hasPermission('courier-bills.manage'))
<details><summary class="btn small primary">+ New Courier Bill</summary>
<form method="post" action="{{ route('courier-bills.store') }}" enctype="multipart/form-data" style="margin-top:12px">
@csrf
<div class="form-grid">
<label>Courier<select name="supplier_id"><option value="">-- New courier --</option>@foreach($courierSuppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
<label>Or new courier name<input name="courier_name"></label>
<label>Bill Date<input type="date" name="bill_date" value="{{ today()->toDateString() }}" required></label>
<label>Period Start<input type="date" name="period_start" required></label>
<label>Period End<input type="date" name="period_end" required></label>
<label>Amount (ex. tax)<input type="number" step="0.01" name="amount_ex_tax" required></label>
<label>Tax<input type="number" step="0.01" name="tax_amount" value="0"></label>
<label>Proof<input type="file" name="proof"></label>
</div>
<p class="muted" style="margin-top:8px">Select the deliveries this bill covers:</p>
<div class="table-wrap" style="max-height:250px;overflow:auto"><table><thead><tr><th></th><th>Delivery</th><th>Customer</th><th>Estimated</th><th>Actual Billed</th></tr></thead><tbody>
@forelse($unbilledDeliveries as $d)
<tr><td><input type="checkbox" name="delivery_ids[]" value="{{ $d->id }}"></td><td>{{ $d->delivery_note_number }}</td><td>{{ $d->customer->name }}</td><td class="amount">AED {{ number_format($d->estimated_cost,2) }}</td><td><input type="number" step="0.01" name="actual_costs[{{ $d->id }}]" value="{{ $d->estimated_cost }}" style="width:90px"></td></tr>
@empty<tr><td colspan="5" class="empty">No unbilled courier deliveries.</td></tr>@endforelse
</tbody></table></div>
<div class="actions" style="margin-top:10px"><button class="btn primary">Create Bill</button></div>
</form>
</details>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Bill #</th><th>Courier</th><th>Period</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead><tbody>
@forelse($bills as $b)<tr><td><a href="{{ route('courier-bills.show',$b) }}"><b>{{ $b->bill_number }}</b></a></td><td>{{ $b->supplier->name }}</td><td>{{ $b->period_start->format('d M') }}–{{ $b->period_end->format('d M Y') }}</td><td class="amount"><b>{{ $b->currency }} {{ number_format($b->total_amount,2) }}</b></td><td class="amount kpi-good">AED {{ number_format($b->amount_paid,2) }}</td><td><span class="badge {{ $b->status==='paid'?'green':($b->status==='partially_paid'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$b->status)) }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No courier bills yet.</td></tr>@endforelse
</tbody></table></div>
</div>

{{-- ================= DRIVER SETTLEMENTS ================= --}}
<div class="tab-pane" data-pane="settlements" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('driver-settlements.manage'))
<form method="post" action="{{ route('driver-settlements.preview') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
@csrf
<label>Driver<select name="driver_id" required><option value="">Select...</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
<label>Start<input type="date" name="start_date" required></label>
<label>End<input type="date" name="end_date" required></label>
<button class="btn primary">Preview Settlement</button>
</form>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Settlement #</th><th>Driver</th><th>Period</th><th>Total Payable</th><th>Paid</th><th>Status</th></tr></thead><tbody>
@forelse($settlements as $s)<tr><td><a href="{{ route('driver-settlements.show',$s) }}"><b>{{ $s->settlement_number }}</b></a></td><td>{{ $s->driver->name }}</td><td>{{ $s->start_date->format('d M') }}–{{ $s->end_date->format('d M Y') }}</td><td class="amount"><b>AED {{ number_format($s->total_payable,2) }}</b></td><td class="amount kpi-good">AED {{ number_format($s->amount_paid,2) }}</td><td><span class="badge {{ $s->status==='paid'?'green':($s->status==='partially_paid'?'amber':'red') }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No settlements yet.</td></tr>@endforelse
</tbody></table></div>
</div>

{{-- ================= VEHICLE EXPENSES ================= --}}
<div class="tab-pane" data-pane="vehicle-expenses" style="margin-top:15px;display:none">
@if(auth()->user()->hasPermission('vehicle-expenses.manage'))
<details><summary class="btn small primary">+ Record Vehicle Expense</summary>
<form method="post" action="{{ route('vehicle-expenses.store') }}" enctype="multipart/form-data" style="margin-top:12px">
@csrf
<div class="form-grid">
<label>Type<select name="expense_type" required><option value="petrol">Petrol</option><option value="maintenance">Maintenance</option><option value="repair">Repair</option><option value="tyres">Tyres</option><option value="registration">Registration</option><option value="insurance">Insurance</option><option value="car_wash">Car Wash</option><option value="parking">Parking</option><option value="toll">Toll</option><option value="other">Other</option></select></label>
<label>Date<input type="date" name="expense_date" value="{{ today()->toDateString() }}" required></label>
<label>Vehicle<select name="vehicle_id"><option value="">--</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></label>
<label>Driver<select name="driver_id"><option value="">--</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
<label>Amount (ex. tax)<input type="number" step="0.01" name="amount_ex_tax" required></label>
<label>Tax<input type="number" step="0.01" name="tax_amount" value="0"></label>
<label>Supplier/Garage<input name="supplier_name"></label>
<label>Payment Method<select name="payment_method"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></label>
<label>Proof<input type="file" name="proof"></label>
</div>
<div class="actions" style="margin-top:8px"><button class="btn primary">Save</button></div>
</form>
</details>
@endif
<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Date</th><th>Type</th><th>Vehicle</th><th>Amount</th><th>Supplier</th></tr></thead><tbody>
@forelse($vehicleExpenses as $e)<tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ ucfirst(str_replace('_',' ',$e->expense_type)) }}</td><td>{{ $e->vehicle?->name?:'—' }}</td><td class="amount"><b>AED {{ number_format($e->total_amount,2) }}</b></td><td>{{ $e->supplier?->name?:'—' }}</td></tr>@empty<tr><td colspan="5" class="empty">No vehicle expenses yet.</td></tr>@endforelse
</tbody></table></div>
</div>

{{-- ================= DRIVERS & VEHICLES ================= --}}
<div class="tab-pane" data-pane="drivers" style="margin-top:15px;display:none">
<div class="grid cols-2">
<div>
<h3>Drivers</h3>
@if(auth()->user()->hasPermission('deliveries.manage'))
<form method="post" action="{{ route('delivery-finance.drivers.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px">
@csrf
<label>Name<input name="name" required></label>
<label>Phone<input name="phone"></label>
<button class="btn small primary">+ Add Driver</button>
</form>
@endif
<div class="table-wrap" style="margin-top:10px"><table><thead><tr><th>Name</th><th>Phone</th></tr></thead><tbody>
@forelse($drivers as $d)<tr><td>{{ $d->name }}</td><td>{{ $d->phone?:'—' }}</td></tr>@empty<tr><td colspan="2" class="empty">No drivers added yet.</td></tr>@endforelse
</tbody></table></div>
</div>
<div>
<h3>Vehicles</h3>
@if(auth()->user()->hasPermission('deliveries.manage'))
<form method="post" action="{{ route('delivery-finance.vehicles.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px">
@csrf
<label>Name<input name="name" placeholder="e.g. Van 1" required></label>
<label>Plate Number<input name="plate_number"></label>
<button class="btn small primary">+ Add Vehicle</button>
</form>
@endif
<div class="table-wrap" style="margin-top:10px"><table><thead><tr><th>Name</th><th>Plate</th></tr></thead><tbody>
@forelse($vehicles as $v)<tr><td>{{ $v->name }}</td><td>{{ $v->plate_number?:'—' }}</td></tr>@empty<tr><td colspan="2" class="empty">No vehicles added yet.</td></tr>@endforelse
</tbody></table></div>
</div>
</div>
</div>

{{-- ================= SETTINGS ================= --}}
<div class="tab-pane" data-pane="settings" style="margin-top:15px;display:none">
<p class="muted">Changing a rate applies from the date given onward — past deliveries keep the rate that was actually in effect for them.</p>
@if(auth()->user()->hasPermission('deliveries.manage'))
<form method="post" action="{{ route('delivery-finance-settings.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
@csrf
<label>Setting<select name="setting_key" required>@foreach($settingKeys as $k=>$label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
<label>New Value (AED)<input type="number" step="0.01" min="0" name="value" required></label>
<label>Effective From<input type="date" name="effective_date" value="{{ today()->toDateString() }}" required></label>
<button class="btn primary">Save</button>
</form>
@endif
<div class="grid cols-4" style="margin-top:15px">
@foreach($settingKeys as $k=>$label)
<div class="stat"><small>{{ $label }}</small><strong>AED {{ number_format($settingsCurrent[$k],2) }}</strong></div>
@endforeach
</div>
</div>

</div>

@push('scripts')
<script>
(function(){
  var initial = window.location.hash ? window.location.hash.replace('#','') : null;
  function activate(target){
    document.querySelectorAll('#finance-tabs .tab-btn').forEach(function(b){ b.classList.toggle('active', b.dataset.tab === target); });
    document.querySelectorAll('.tab-pane').forEach(function(pane){ pane.style.display = pane.dataset.pane === target ? '' : 'none'; });
  }
  document.querySelectorAll('#finance-tabs .tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){ activate(btn.dataset.tab); });
  });
  if (initial && document.querySelector('.tab-pane[data-pane="'+initial+'"]')) { activate(initial); }
})();
</script>
@endpush
@endsection
