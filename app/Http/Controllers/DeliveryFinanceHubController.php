<?php

namespace App\Http\Controllers;

use App\Models\{CourierBill, DeliveryFinanceSetting, DeliveryNote, DriverSettlement, Role, Supplier, User, Vehicle, VehicleExpense};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A single hub page for everything delivery-finance related — courier
 * bills, driver settlements, vehicle expenses, drivers & vehicles, and
 * settings — as tabs on one page instead of four separate sidebar
 * items. The individual create/pay actions still go through their own
 * focused controllers (CourierBillController etc.); this controller
 * only aggregates what each tab needs to display and adds the quick
 * "add a driver" / "add a vehicle" actions that were previously buried
 * behind the full Users & Roles admin flow.
 */
class DeliveryFinanceHubController extends Controller
{
    private const SETTING_KEYS = [
        'domestic_customer_charge' => 'Domestic Customer Delivery Charge',
        'domestic_courier_estimated_cost' => 'Domestic Outside Courier — Estimated Cost',
        'own_driver_fee' => 'Own-Driver Fee per Completed Delivery',
        'own_driver_daily_allowance' => 'Own-Driver Daily Phone/Internet Allowance',
    ];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        $tab = $request->query('tab', 'courier-bills');

        $drivers = User::whereHas('roles', fn ($q) => $q->where('name', 'driver'))->orderBy('name')->get();
        $vehicles = Vehicle::orderBy('name')->get();

        return view('deliveries.finance-hub', [
            'tab' => $tab,
            'bills' => CourierBill::with('supplier', 'lines')->latest('bill_date')->limit(20)->get(),
            'settlements' => DriverSettlement::with('driver')->latest('end_date')->limit(20)->get(),
            'vehicleExpenses' => VehicleExpense::with('vehicle', 'driver', 'supplier')->latest('expense_date')->limit(20)->get(),
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'unbilledDeliveries' => DeliveryNote::whereIn('delivery_type', ['domestic_outside_courier', 'international_courier'])->whereNull('courier_bill_id')->with('customer')->orderByDesc('delivery_date')->limit(100)->get(),
            'courierSuppliers' => Supplier::where('supplier_type', 'delivery_courier')->orWhereNull('supplier_type')->orderBy('name')->get(),
            'settingKeys' => self::SETTING_KEYS,
            'settingsCurrent' => collect(self::SETTING_KEYS)->keys()->mapWithKeys(fn ($k) => [$k => DeliveryFinanceSetting::valueOn($k, now())]),
        ]);
    }

    /** Adds a driver in one step — name and phone only, no separate trip through Users & Roles. A login password is generated but drivers are not expected to need to sign in. */
    public function storeDriver(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:190', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:190|unique:users,email']);

        $driver = User::create([
            'name' => $data['name'], 'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? (Str::slug($data['name']).'-'.Str::random(5).'@drivers.local'),
            'password' => Str::random(24), 'is_active' => true,
        ]);
        $driverRole = Role::where('name', 'driver')->first();
        if ($driverRole) {
            $driver->roles()->attach($driverRole);
        }

        return back()->with('success', "Driver {$driver->name} added — available for delivery assignment right away.")->withFragment('drivers');
    }

    public function storeVehicle(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'plate_number' => 'nullable|string|max:30']);
        Vehicle::create($data);
        return back()->with('success', 'Vehicle added.')->withFragment('drivers');
    }
}
