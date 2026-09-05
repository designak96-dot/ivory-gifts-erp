<?php

namespace App\Http\Controllers;

use App\Models\{CourierBill, DeliveryNote, Supplier};
use App\Services\{DeliveryFinanceService, ProofUploadService};
use Illuminate\Http\Request;

class CourierBillController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        $bills = CourierBill::with('supplier', 'lines')->latest('bill_date')->paginate(20);
        return view('deliveries.courier-bills.index', compact('bills'));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('courier-bills.manage'), 403);
        // Only unbilled outside/international courier deliveries can be picked — the DB unique constraint backs this up too.
        $unbilledDeliveries = DeliveryNote::whereIn('delivery_type', ['domestic_outside_courier', 'international_courier'])
            ->whereNull('courier_bill_id')
            ->with('customer', 'salesOrder')->orderByDesc('delivery_date')->limit(200)->get();
        $suppliers = Supplier::where('supplier_type', 'delivery_courier')->orWhereNull('supplier_type')->orderBy('name')->get();
        return view('deliveries.courier-bills.create', compact('unbilledDeliveries', 'suppliers'));
    }

    public function store(Request $request, DeliveryFinanceService $service, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('courier-bills.manage'), 403);
        $data = $request->validate([
            'courier_name' => 'required_without:supplier_id|string|max:190', 'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_invoice_number' => 'nullable|string|max:100', 'bill_date' => 'required|date',
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'currency' => 'nullable|string|max:3', 'amount_ex_tax' => 'required|numeric|min:0', 'tax_amount' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0', 'notes' => 'nullable|string',
            'delivery_ids' => 'required|array|min:1', 'delivery_ids.*' => 'exists:delivery_notes,id',
            'actual_costs' => 'required|array', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);

        $supplier = !empty($data['supplier_id']) ? Supplier::findOrFail($data['supplier_id']) : $service->findOrCreateCourierSupplier($data['courier_name']);
        $totalExTax = (float) $data['amount_ex_tax'];
        $tax = (float) ($data['tax_amount'] ?? 0);
        $total = round($totalExTax + $tax, 2);
        $currency = $data['currency'] ?? 'AED';
        $aedEquivalent = $currency === 'AED' ? $total : round($total * (float) ($data['exchange_rate'] ?? 1), 2);

        $billData = [
            'supplier_id' => $supplier->id, 'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
            'bill_date' => $data['bill_date'], 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
            'currency' => $currency, 'amount_ex_tax' => $totalExTax, 'tax_amount' => $tax, 'total_amount' => $total,
            'exchange_rate' => $data['exchange_rate'] ?? null, 'aed_equivalent' => $aedEquivalent,
            'status' => 'received', 'notes' => $data['notes'] ?? null,
        ];
        if ($request->hasFile('proof')) {
            $stored = $proofs->store($request->file('proof'), 'courier-bill-proofs');
            $billData['proof_path'] = $stored['proof_path'];
            $billData['proof_original_name'] = $stored['proof_original_name'];
        }

        $lines = [];
        foreach ($data['delivery_ids'] as $id) {
            $lines[] = ['delivery_note_id' => $id, 'actual_billed_cost' => (float) ($data['actual_costs'][$id] ?? 0)];
        }

        try {
            $bill = $service->createCourierBill($billData, $lines, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['delivery_ids' => $e->getMessage()])->withInput();
        }

        return redirect()->route('courier-bills.show', $bill)->with('success', "Courier bill {$bill->bill_number} created with ".count($lines).' delivery lines.');
    }

    public function show(CourierBill $bill)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.view.finance'), 403);
        return view('deliveries.courier-bills.show', ['bill' => $bill->load('supplier', 'lines.delivery.customer')]);
    }

    public function pay(Request $request, CourierBill $bill, DeliveryFinanceService $service)
    {
        abort_unless(auth()->user()->hasPermission('courier-bills.pay'), 403);
        $data = $request->validate(['amount_paid' => 'required|numeric|min:0.01', 'payment_date' => 'required|date', 'payment_method' => 'required|in:cash,bank,card', 'payment_reference' => 'nullable|string|max:100']);
        $service->payCourierBill($bill, (float) $data['amount_paid'], $data, auth()->id());
        return back()->with('success', 'Payment recorded — expense posted or updated automatically.');
    }

    public function approve(CourierBill $bill)
    {
        abort_unless(auth()->user()->hasPermission('courier-bills.approve'), 403);
        $bill->update(['status' => $bill->amount_paid > 0 ? $bill->status : 'approved']);
        return back()->with('success', 'Bill approved.');
    }
}
