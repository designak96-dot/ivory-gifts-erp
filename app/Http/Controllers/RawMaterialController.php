<?php

namespace App\Http\Controllers;

use App\Models\{ChartOfAccount, RawMaterial, RawMaterialPurchase, Supplier};
use App\Services\{ProofUploadService, RawMaterialPurchaseService};
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    /** Unified "Purchases & Suppliers" hub: Suppliers, Raw Materials, Record Purchase, Purchase History, Supplier Price Comparison — nothing else. */
    public function index(Request $request)
    {
        $q = RawMaterial::with('preferredSupplier')->withCount('purchaseLines');
        if ($s = $request->query('q')) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        return view('raw-materials.index', [
            'materials' => $q->orderBy('name')->paginate(25),
            'suppliers' => Supplier::orderBy('name')->get(),
            'bankAccounts' => ChartOfAccount::where('account_subtype', 'bank')->where('is_active', true)->get(),
            'recentPurchases' => RawMaterialPurchase::with('supplier', 'lines.rawMaterial')->latest('purchase_date')->limit(15)->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('purchases.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:190', 'code' => 'required|string|max:50|unique:raw_materials,code',
            'category' => 'nullable|string|max:100', 'unit' => 'required|string|max:30',
            'reorder_level' => 'nullable|numeric|min:0', 'preferred_supplier_id' => 'nullable|exists:suppliers,id',
        ]);
        $material = RawMaterial::create($data);
        return back()->with('success', "Raw material {$material->name} added.");
    }

    public function show(RawMaterial $material, RawMaterialPurchaseService $service)
    {
        return view('raw-materials.show', [
            'material' => $material->load('preferredSupplier'),
            'purchaseLines' => $material->purchaseLines()->with('purchase.supplier')->latest('id')->paginate(25),
            'priceHistory' => $service->priceHistory($material),
        ]);
    }

    /** One supplier invoice, multiple material lines — a real header + lines purchase, not one entry per material. */
    public function storePurchase(Request $request, RawMaterialPurchaseService $service, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('purchases.manage'), 403);
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank,unpaid',
            'bank_account_id' => 'required_if:payment_method,bank|nullable|exists:chart_of_accounts,id',
            'payment_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'invoice' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'lines' => 'required|array|min:1',
            'lines.*.raw_material_id' => 'required|exists:raw_materials,id',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.unit' => 'required|string|max:30',
            'lines.*.unit_price' => 'required|numeric|min:0.0001',
            'lines.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        if ($request->hasFile('invoice')) {
            $raw = $proofs->store($request->file('invoice'), 'raw-material-invoices');
            $data['invoice_fields'] = ['invoice_path' => $raw['proof_path'], 'invoice_original_name' => $raw['proof_original_name'], 'invoice_mime' => $raw['proof_mime'], 'invoice_size' => $raw['proof_size']];
        }

        $purchase = $service->create($data);
        $materialCount = count($data['lines']);
        return redirect()->route('raw-materials.index')->with('success', "Purchase {$purchase->purchase_number} recorded — {$materialCount} material(s) updated, stock and accounting posted automatically.");
    }

    public function downloadInvoice(RawMaterialPurchase $purchase)
    {
        abort_unless(auth()->user()->hasPermission('purchases.view'), 403);
        abort_unless($purchase->invoice_path, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($purchase->invoice_path), 404);
        if (request()->boolean('download')) {
            return \Illuminate\Support\Facades\Storage::disk('local')->download($purchase->invoice_path, $purchase->invoice_original_name);
        }
        return \Illuminate\Support\Facades\Storage::disk('local')->response($purchase->invoice_path, $purchase->invoice_original_name, ['Content-Type' => $purchase->invoice_mime]);
    }
}
