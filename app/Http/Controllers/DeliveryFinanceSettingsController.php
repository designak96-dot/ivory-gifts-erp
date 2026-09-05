<?php
namespace App\Http\Controllers;
use App\Models\DeliveryFinanceSetting;
use Illuminate\Http\Request;
class DeliveryFinanceSettingsController extends Controller
{
    private const KEYS = [
        'domestic_customer_charge' => 'Domestic Customer Delivery Charge',
        'domestic_courier_estimated_cost' => 'Domestic Outside Courier — Estimated Cost',
        'own_driver_fee' => 'Own-Driver Fee per Completed Delivery',
        'own_driver_daily_allowance' => 'Own-Driver Daily Phone/Internet Allowance',
    ];

    public function index()
    {
        abort_unless(auth()->user()->hasPermission('deliveries.manage'), 403);
        $history = [];
        foreach (self::KEYS as $key => $label) {
            $history[$key] = DeliveryFinanceSetting::where('setting_key', $key)->orderByDesc('effective_date')->get();
        }
        return view('deliveries.finance-settings', ['keys' => self::KEYS, 'history' => $history]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.manage'), 403);
        $data = $request->validate(['setting_key' => 'required|in:'.implode(',', array_keys(self::KEYS)), 'value' => 'required|numeric|min:0', 'effective_date' => 'required|date']);
        // A new effective-dated row, never overwriting the old one — historical deliveries keep the rate that was actually in effect for them.
        DeliveryFinanceSetting::create($data + ['created_by' => auth()->id()]);
        return back()->with('success', 'New rate saved — takes effect from the date given, past deliveries are unaffected.');
    }
}
