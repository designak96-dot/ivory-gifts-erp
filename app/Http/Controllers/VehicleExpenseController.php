<?php

namespace App\Http\Controllers;

use App\Models\{DeliveryNote, Vehicle, VehicleExpense};
use App\Services\{DeliveryFinanceService, ProofUploadService};
use Illuminate\Http\Request;

class VehicleExpenseController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        $expenses = VehicleExpense::with('vehicle', 'driver', 'supplier')->latest('expense_date')->paginate(25);
        $vehicles = Vehicle::where('is_active', true)->orderBy('name')->get();
        $drivers = \App\Models\User::whereIn('id', DeliveryNote::where('delivery_type', 'own_company')->whereNotNull('driver_id')->distinct()->pluck('driver_id'))->orderBy('name')->get();
        return view('deliveries.vehicle-expenses.index', compact('expenses', 'vehicles', 'drivers'));
    }

    public function store(Request $request, DeliveryFinanceService $service, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('vehicle-expenses.manage'), 403);
        $data = $request->validate([
            'expense_type' => 'required|in:petrol,maintenance,repair,tyres,registration,insurance,car_wash,parking,toll,other',
            'expense_date' => 'required|date', 'vehicle_id' => 'nullable|exists:vehicles,id', 'driver_id' => 'nullable|exists:users,id',
            'amount_ex_tax' => 'required|numeric|min:0', 'tax_amount' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:190', 'invoice_reference' => 'nullable|string|max:100', 'payment_method' => 'nullable|in:cash,bank,card',
            'odometer_reading' => 'nullable|integer|min:0', 'maintenance_type' => 'nullable|string|max:100', 'description' => 'nullable|string|max:255',
            'next_service_date' => 'nullable|date', 'notes' => 'nullable|string', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);
        if (!empty($data['supplier_name'])) {
            $data['supplier_id'] = $service->findOrCreateSupplierByName($data['supplier_name'])->id;
            unset($data['supplier_name']);
        }
        if ($request->hasFile('proof')) {
            $stored = $proofs->store($request->file('proof'), 'vehicle-expense-proofs');
            $data['proof_path'] = $stored['proof_path'];
        }

        $expense = $service->saveVehicleExpense($data, auth()->id());
        return back()->with('success', "Vehicle expense recorded — {$expense->expense_type} expense posted.");
    }

    public function allocate(Request $request, VehicleExpense $vehicleExpense, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('vehicle-expenses.manage'), 403);
        $data = $request->validate(['delivery_ids' => 'required|array|min:1', 'delivery_ids.*' => 'exists:delivery_notes,id']);
        $service->allocateVehicleExpense($vehicleExpense, $data['delivery_ids']);
        return back()->with('success', 'Allocated across '.count($data['delivery_ids']).' deliveries for reporting — no new accounting entry created.');
    }

    public function storeVehicle(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle-expenses.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'plate_number' => 'nullable|string|max:30']);
        Vehicle::create($data);
        return back()->with('success', 'Vehicle added.');
    }
}
