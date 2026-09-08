<?php

namespace App\Http\Controllers;

use App\Models\{DriverSettlement, User};
use App\Services\{DeliveryFinanceService, ProofUploadService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DriverSettlementController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        $settlements = DriverSettlement::with('driver')->latest('end_date')->paginate(20);
        $roleBasedDriverIds = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['driver', 'delivery_coordinator']))->pluck('id');
        $assignedDriverIds = \App\Models\DeliveryNote::whereNotNull('driver_id')->distinct()->pluck('driver_id');
        $drivers = User::whereIn('id', $roleBasedDriverIds->merge($assignedDriverIds)->unique())->orderBy('name')->get();
        return view('deliveries.driver-settlements.index', compact('settlements', 'drivers'));
    }

    public function preview(Request $request, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('driver-settlements.manage'), 403);
        $data = $request->validate(['driver_id' => 'required|exists:users,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);
        $preview = $service->buildDriverSettlementPreview((int) $data['driver_id'], \Carbon\Carbon::parse($data['start_date']), \Carbon\Carbon::parse($data['end_date']));
        $driver = User::findOrFail($data['driver_id']);
        return view('deliveries.driver-settlements.preview', compact('preview', 'driver', 'data'));
    }

    public function store(Request $request, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('driver-settlements.manage'), 403);
        $data = $request->validate(['driver_id' => 'required|exists:users,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);
        $settlement = $service->createDriverSettlement((int) $data['driver_id'], \Carbon\Carbon::parse($data['start_date']), \Carbon\Carbon::parse($data['end_date']), auth()->id());
        return redirect()->route('driver-settlements.show', $settlement)->with('success', "Settlement {$settlement->settlement_number} created — AED ".number_format($settlement->total_payable, 2).' payable.');
    }

    public function show(DriverSettlement $settlement)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        return view('deliveries.driver-settlements.show', ['settlement' => $settlement->load('driver', 'deliveries', 'dailyAllowances')]);
    }

    public function pay(Request $request, DriverSettlement $settlement, DeliveryFinanceService $service, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('driver-settlements.pay'), 403);
        $data = $request->validate([
            'amount_paid' => 'required|numeric|min:0.01', 'payment_date' => 'required|date', 'payment_method' => 'required|in:cash,bank,card',
            'payment_reference' => 'nullable|string|max:100', 'proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'idempotency_key' => 'nullable|string|max:100',
        ]);
        $stored = $proofs->store($request->file('proof'), 'driver-settlement-payment-proofs');
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();
        $service->paySettlement($settlement, (float) $data['amount_paid'], $data, $idempotencyKey, $stored['proof_path'], $stored['proof_original_name'], auth()->id());
        return back()->with('success', 'Payment recorded — expense posted or updated automatically.');
    }

    public function report(Request $request, \App\Services\DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.profit'), 403);
        $data = $request->validate(['driver_id' => 'required|exists:users,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);
        $driver = User::findOrFail($data['driver_id']);
        $report = $service->buildDriverReport((int) $data['driver_id'], \Carbon\Carbon::parse($data['start_date']), \Carbon\Carbon::parse($data['end_date']));
        $settings = \App\Models\Setting::pluck('value', 'key');
        return view('deliveries.driver-report', compact('driver', 'report', 'data', 'settings'));
    }
}
