<?php

namespace App\Http\Controllers;

use App\Models\{DriverSettlement, User};
use App\Services\DeliveryFinanceService;
use Illuminate\Http\Request;

class DriverSettlementController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        $settlements = DriverSettlement::with('driver')->latest('end_date')->paginate(20);
        $driverIds = \App\Models\DeliveryNote::where('delivery_type', 'own_company')->whereNotNull('driver_id')->distinct()->pluck('driver_id');
        $drivers = User::whereIn('id', $driverIds)->orderBy('name')->get();
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

    public function pay(Request $request, DriverSettlement $settlement, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('driver-settlements.pay'), 403);
        $data = $request->validate(['amount_paid' => 'required|numeric|min:0.01', 'payment_date' => 'required|date', 'payment_method' => 'required|in:cash,bank,card', 'payment_reference' => 'nullable|string|max:100']);
        $service->paySettlement($settlement, (float) $data['amount_paid'], $data, auth()->id());
        return back()->with('success', 'Payment recorded — expense posted or updated automatically.');
    }
}
