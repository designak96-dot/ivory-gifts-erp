<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Product, ProductionJob, SalesOrder, SalesOrderStatusHistory, TaxRate, User};
use App\Services\{AccountingService, DeliverySchedulingService, DocumentTotals, NumberingService, PhoneNormalizer, SalesWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public const LOCATIONS = [
        'Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain',
        'Ras Al Khaimah', 'Fujairah', 'Western Region', 'Personal Pickup',
        'Saudi Arabia', 'Oman', 'Qatar', 'Kuwait', 'Bahrain', 'Other GCC',
    ];

    public function index()
    {
        $query = SalesOrder::with('customer', 'driver');
        if (!request()->boolean('show_legacy')) $query->where('is_legacy_delivery_import', false);
        if ($search = request('q')) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }
        if (request('month')) {
            $month = \Carbon\Carbon::createFromFormat('Y-m', request('month'));
            $query->whereDate('order_month', $month->startOfMonth());
        }
        if (request('delivery_status')) $query->where('delivery_status', request('delivery_status'));
        if (request('design_status')) $query->where('design_status', request('design_status'));
        if (request('due') === 'this_week') {
            $query->whereBetween('delivery_date', [today(), today()->endOfWeek()])->where('delivery_status', '!=', 'delivered');
        }

        return view('orders.index', ['orders' => $query->orderByDesc('is_very_urgent')->orderBy('delivery_date')->paginate(30)->withQueryString()]);
    }

    public function checkCapacity(Request $request, DeliverySchedulingService $scheduler)
    {
        $request->validate(['date' => 'required|date']);
        $date = \Carbon\Carbon::parse($request->query('date'));
        $limit = $scheduler->dailyLimit();
        $count = $scheduler->scheduledCount($date);
        $full = $count >= $limit;
        return response()->json([
            'date' => $date->toDateString(),
            'count' => $count,
            'limit' => $limit,
            'full' => $full,
            'suggested_date' => $full ? $scheduler->nextAvailableDate($date->copy()->addDay())->toDateString() : null,
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        return view('orders.form', $this->formData(new SalesOrder));
    }

    /**
     * "Repeat Order" — pre-fills a fresh create form with the customer
     * and line items (products, customisation, prices as editable
     * defaults) from a past order, but deliberately does NOT flash
     * order_date or delivery_date, since a new order requires its own
     * fresh dates rather than inheriting the original's.
     */
    public function repeat(SalesOrder $order)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $order->loadMissing('items.product', 'customer');

        $items = $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_label' => $item->product ? $item->product->name_en.($item->product->sku ? ' · '.$item->product->sku : '') : null,
            'description' => $item->description,
            'qty' => $item->qty,
            'unit_price' => $item->unit_price,
            'tax_rate' => 5,
            'customisation' => $item->customisation['notes'] ?? '',
            'is_manual' => !$item->product_id,
        ])->all();

        return redirect()->route('orders.create')->withInput([
            'customer_label' => $order->customer ? $order->customer->name.($order->customer->phone ? ' · '.$order->customer->phone : '') : '',
            'customer_id' => $order->customer_id,
            'customer_phone' => $order->customer_phone,
            'items' => $items,
        ])->with('info', "Repeating Order {$order->order_number} — review products and prices, then set a new order date and delivery date.");
    }

    public function store(Request $request, NumberingService $numbers, DocumentTotals $totals, SalesWorkflow $workflow)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $data = $this->validated($request);
        $calculated = $totals->calculate($data['items']);
        $orderDate = \Carbon\Carbon::parse($data['order_date']);
        $orderNumber = $numbers->formatSalesOrderNumber($data['manual_reference'], $orderDate);

        // Checked up front so the common case gives an immediate, specific
        // message without even starting a transaction; still caught again
        // below for the rare case of two people submitting the same
        // manual+date combination at the same instant.
        if (SalesOrder::where('order_number', $orderNumber)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'manual_reference' => "Sales Order {$orderNumber} already exists. Please enter another Order Number.",
            ]);
        }

        try {
            $order = DB::transaction(function () use ($data, $calculated, $numbers, $orderDate, $orderNumber) {
                $customer = Customer::findOrFail($data['customer_id']);
                $order = SalesOrder::create($this->orderFields($data, $customer) + [
                    'order_number' => $orderNumber,
                    'manual_reference' => strtoupper($data['manual_reference']),
                    'order_month' => $orderDate->copy()->startOfMonth(),
                    'subtotal' => $calculated['subtotal'],
                    'tax_total' => $calculated['tax_total'],
                    'grand_total' => $calculated['grand_total'],
                ]);
                $this->replaceItems($order, $calculated['items']);
                ProductionJob::create([
                    'job_number' => $numbers->next('production_job'),
                    'sales_order_id' => $order->id,
                    'due_date' => $order->delivery_date,
                    'stage' => 'waiting_for_design',
                    'sale_value' => $order->grand_total,
                    'estimated_profit' => $order->grand_total,
                ]);
                return $order;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'manual_reference' => "Sales Order {$orderNumber} already exists. Please enter another Order Number.",
                ]);
            }
            throw $e;
        }
        $workflow->createDelivery($order->load('customer', 'invoices'));

        return redirect()->route('orders.show', $order)->with('success', 'Sales order created and added to the delivery schedule.');
    }

    /**
     * Owner-only (enforced by the orders.delete permission on the route,
     * which is granted only to the Owner role). Refuses to delete an order
     * that has any invoice with a payment already applied — deleting that
     * would silently corrupt real payment history. Soft-deletes the order
     * and any of its own draft/unpaid invoices together so nothing is left
     * orphaned; posted journal entries for those invoices are reversed,
     * never deleted, so the accounting trail stays intact.
     */
    public function destroy(SalesOrder $order, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('orders.delete'), 403);

        $order->loadMissing('invoices');
        if ($order->invoices->contains(fn ($invoice) => (float) $invoice->amount_paid > 0)) {
            return back()->withErrors(['delete' => 'This order has an invoice with payments already applied and cannot be deleted. Delete the payment(s) first if this is genuinely required.']);
        }

        DB::transaction(function () use ($order, $accounting) {
            foreach ($order->invoices as $invoice) {
                $accounting->reverse($invoice, "Sales order {$order->order_number} deleted");
                $invoice->delete();
            }
            $order->productionJob?->delete();
            $order->deliveryNote?->delete();
            $order->delete();
        });

        return redirect()->route('orders.index')->with('success', "Sales order {$order->order_number} deleted.");
    }

    public function edit(SalesOrder $order)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        abort_if($order->is_legacy_delivery_import, 403, 'Imported delivery history is edited from Deliveries.');
        return view('orders.form', $this->formData($order->load('items')));
    }

    public function update(Request $request, SalesOrder $order, DocumentTotals $totals, SalesWorkflow $workflow, DeliverySchedulingService $scheduler)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        abort_if($order->is_legacy_delivery_import, 403);
        $data = $this->validated($request);
        $calculated = $totals->calculate($data['items']);
        $delivery = $order->deliveryNote;
        if ($delivery && $delivery->delivery_date?->toDateString() !== $data['delivery_date']) {
            try { $scheduler->ensureAvailable($data['delivery_date'], $delivery->id); }
            catch (\RuntimeException $e) { throw ValidationException::withMessages(['delivery_date' => $e->getMessage()]); }
        }
        DB::transaction(function () use ($order, $data, $calculated, $delivery) {
            $customer = Customer::findOrFail($data['customer_id']);
            $order->update($this->orderFields($data, $customer) + [
                'order_month' => \Carbon\Carbon::parse($data['order_date'])->startOfMonth(),
                'subtotal' => $calculated['subtotal'],
                'tax_total' => $calculated['tax_total'],
                'grand_total' => $calculated['grand_total'],
            ]);
            $this->replaceItems($order, $calculated['items']);
            $order->productionJob?->update(['due_date' => $order->delivery_date, 'sale_value' => $order->grand_total]);
            $delivery?->update([
                'customer_id' => $order->customer_id,
                'delivery_date' => $order->delivery_date,
                'driver_id' => $order->driver_id,
                'last_updated_by' => auth()->id(),
            ]);
        });
        if (!$delivery) $workflow->createDelivery($order->fresh(['customer', 'invoices']));

        return redirect()->route('orders.show', $order)->with('success', 'Sales order and delivery schedule updated.');
    }

    /** Autocomplete: name, phone, or customer code. */
    public function searchCustomers(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage') || auth()->user()->hasPermission('quotations.manage'), 403);
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $digits = preg_replace('/\D+/', '', $term);
        // Stored phones are always normalized (leading 0 stripped, country
        // code prepended) — a raw local-format search like "0543927290"
        // would never match the stored "+971543927290" by substring alone.
        // Searching for both the raw digits and the leading-zero-stripped
        // version covers local format, international format, and partial
        // digit searches all in one query.
        $digitsNoLeadingZero = ltrim($digits, '0');
        $customers = Customer::where('status', 'active')
            ->where(function ($q) use ($term, $digits, $digitsNoLeadingZero) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('customer_code', 'like', "%{$term}%");
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                    if ($digitsNoLeadingZero !== '' && $digitsNoLeadingZero !== $digits) {
                        $q->orWhere('phone', 'like', "%{$digitsNoLeadingZero}%");
                    }
                }
            })
            ->orderBy('name')->limit(15)->get();

        return response()->json($customers->map(fn ($c) => $this->customerPayload($c))->values());
    }

    /** Autocomplete: product name or SKU. */
    public function searchProducts(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage') || auth()->user()->hasPermission('quotations.manage'), 403);
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $products = Product::with('taxRate')->where('is_active', true)
            ->where(fn ($q) => $q->where('name_en', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))
            ->orderBy('name_en')->limit(15)->get();

        return response()->json($products->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name_en, 'sku' => $p->sku,
            'price' => $p->sale_price, 'tax' => $p->taxRate?->rate ?? 5,
            'thumb' => $p->thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($p->thumbnail_path) : null,
        ])->values());
    }

    public function quickCustomer(Request $request, NumberingService $numbers, PhoneNormalizer $phones)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:190', 'phone' => 'required|string|max:30',
            'emirate' => 'nullable|string|max:50', 'area' => 'nullable|string|max:100',
            'delivery_address' => 'nullable|string|max:2000',
        ]);
        try { $data['phone'] = $phones->normalize($data['phone']); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['phone' => $e->getMessage()]); }
        if ($existing = Customer::where('phone', $data['phone'])->first()) {
            return response()->json([
                'duplicate' => true,
                'message' => 'Existing customer found with this phone number.',
                'customer' => $this->customerPayload($existing),
            ], 409);
        }
        $customer = Customer::create($data + [
            'customer_code' => $numbers->next('customer'), 'whatsapp' => $data['phone'],
            'status' => 'active', 'preferred_language' => 'en', 'customer_type' => 'retail',
            'source' => 'sales_order', 'created_by' => auth()->id(), 'updated_by' => auth()->id(),
        ]);
        return response()->json($this->customerPayload($customer), 201);
    }

    /**
     * Same validation, SKU generation, and image handling as the main
     * Products module — via ProductService — so this popup is genuinely
     * the same form/logic, not a second thinner implementation.
     */
    public function quickProduct(Request $request, \App\Services\ProductService $products)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $data = $request->validate(\App\Services\ProductService::validationRules());
        $product = $products->create($data, $request->file('image'));
        $product->load('taxRate');

        return response()->json([
            'id' => $product->id,
            'name' => $product->name_en,
            'sku' => $product->sku,
            'price' => $product->sale_price,
            'tax' => $product->taxRate?->rate ?? 0,
            'thumb' => $product->thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($product->thumbnail_path) : null,
        ], 201);
    }

    public function show(SalesOrder $order)
    {
        $order->load('customer', 'items.product', 'productionJob.costs', 'deliveryNote', 'invoices', 'statusHistory.changedBy', 'comments.user', 'attachments.uploader');
        $timeline = $order->statusHistory->sortBy('created_at')->values();
        $profit = auth()->user()->hasPermission('reports.financial') ? app(\App\Services\ProfitCalculatorService::class)->forOrder($order) : null;
        return view('orders.show', ['order' => $order, 'profit' => $profit, 'timeline' => $timeline, 'drivers' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['driver', 'delivery_coordinator']))->where('is_active', true)->get()]);
    }

    /** The new simplified Status field (Pending/Ready/Delivered/Canceled) — replaces the old Confirmation/Design/Production/Delivery workflow in the Sales UI. Goes through SimpleWorkflowService so Deliveries always sees the identical value. */
    public function updateSimpleStatus(Request $request, SalesOrder $order, \App\Services\SimpleWorkflowService $workflow)
    {
        $data = $request->validate([
            'simple_status' => 'required|in:pending,ready,delivered,canceled',
            'fulfillment_type' => 'sometimes|in:delivery,pickup',
            'driver_id' => 'nullable|exists:users,id',
            'delivery_date' => 'nullable|date',
        ]);
        $old = $order->simple_status;
        $workflow->setStatus($order, $data['simple_status']);
        if ($old !== $data['simple_status']) {
            SalesOrderStatusHistory::create(['sales_order_id' => $order->id, 'field' => 'status', 'old_value' => $old, 'new_value' => $data['simple_status'], 'changed_by' => auth()->id()]);
        }
        $order->update(collect($data)->only(['fulfillment_type', 'driver_id', 'delivery_date'])->all());
        return back()->with('success', 'Status updated.');
    }

    public function updateStatus(Request $request, SalesOrder $order)
    {
        $data = $request->validate(['confirmation_status'=>'sometimes|in:waiting,waiting_for_deposit,confirmed,cancelled','design_status'=>'sometimes|in:need_design,designing,waiting_customer,designed','production_status'=>'sometimes|in:waiting,materials_pending,in_production,quality_check,ready,completed','delivery_status'=>'sometimes|in:not_scheduled,scheduled,out_for_delivery,delivered,failed,returned','priority'=>'sometimes|in:normal,urgent,high','driver_id'=>'nullable|exists:users,id','delivery_date'=>'nullable|date']);
        DB::transaction(function () use ($order, $data) {
            foreach ($data as $field => $value) {
                $old = $order->{$field};
                if ((string)$old !== (string)$value) SalesOrderStatusHistory::create(['sales_order_id'=>$order->id,'field'=>$field,'old_value'=>$old,'new_value'=>$value??'','changed_by'=>auth()->id()]);
            }
            $order->update($data);
            if (($data['production_status'] ?? null) === 'ready') $order->productionJob?->update(['stage' => 'ready']);
        });
        return back()->with('success', 'Order updated.');
    }

    public function invoice(SalesOrder $order, SalesWorkflow $workflow)
    {
        try { $invoice = $workflow->orderToInvoice($order->load('items', 'customer')); return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice posted to accounting.'); }
        catch (\Throwable $e) { return back()->withErrors(['invoice' => $e->getMessage()]); }
    }

    public function delivery(SalesOrder $order, SalesWorkflow $workflow)
    {
        $note = $workflow->createDelivery($order);
        return redirect()->route('deliveries.show', $note)->with('success', 'Delivery note ready.');
    }

    private function validated(Request $request): array
    {
        // Manual lines (no product_id) are kept, not filtered out — only
        // drop a line if it has neither a real product_id nor any
        // description at all (a genuinely empty row).
        $items = collect($request->input('items', []))
            ->filter(function ($item) {
                $productId = $item['product_id'] ?? null;
                $description = trim((string) ($item['description'] ?? ''));
                if ($productId) {
                    return Product::whereKey($productId)->exists();
                }
                return $description !== '';
            })
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        return $request->validate([
            'customer_id'=>'required|exists:customers,id','customer_phone'=>'nullable|string|max:30',
            'manual_reference'=>'required|string|max:10|regex:/^[A-Za-z0-9]+$/',
            'order_date'=>'required|date','delivery_date'=>'required|date','emirate'=>'required|string|max:50',
            'delivery_address'=>'nullable|string|max:2000','priority'=>'required|in:normal,urgent,high',
            'fulfillment_type'=>'sometimes|in:delivery,pickup',
            'is_very_urgent'=>'nullable|boolean','notes'=>'nullable|string|max:5000','items'=>'required|array|min:1',
            'items.*.product_id'=>'nullable|exists:products,id',
            'items.*.description'=>'required_without:items.*.product_id|nullable|string|max:255',
            'items.*.qty'=>'required|numeric|min:0.001',
            'items.*.unit_price'=>'required|numeric|min:0','items.*.tax_rate'=>'nullable|numeric|min:0|max:100',
            'items.*.customisation'=>'nullable|string|max:2000',
        ]);
    }

    private function orderFields(array $data, Customer $customer): array
    {
        return [
            'customer_id'=>$customer->id,'customer_phone'=>($data['customer_phone'] ?? null) ?: $customer->phone,
            'order_date'=>$data['order_date'],'delivery_date'=>$data['delivery_date'],'emirate'=>$data['emirate'],
            'delivery_address'=>($data['delivery_address'] ?? null) ?: $customer->delivery_address,'priority'=>$data['priority'],
            'fulfillment_type'=>$data['fulfillment_type'] ?? 'delivery',
            'is_very_urgent'=>$data['is_very_urgent'] ?? false,'notes'=>$data['notes'] ?? null,
        ];
    }

    private function replaceItems(SalesOrder $order, array $items): void
    {
        $order->items()->delete();
        foreach ($items as $item) {
            $product = !empty($item['product_id']) ? Product::find($item['product_id']) : null;
            $order->items()->create(['product_id'=>$product?->id,'description'=>$item['description']??$product?->name_en??'Item','qty'=>$item['qty'],'unit_price'=>$item['unit_price'],'tax_amount'=>$item['tax_amount'],'line_total'=>$item['line_total'],'customisation'=>['notes'=>$item['customisation']??null]]);
        }
    }

    private function formData(SalesOrder $order): array
    {
        return ['order'=>$order,'customers'=>Customer::where('status','active')->orderBy('name')->get(),'products'=>Product::with('taxRate')->where('is_active',true)->orderBy('name_en')->get(),'locations'=>self::LOCATIONS,'productCategories'=>\App\Models\ProductCategory::orderBy('name_en')->get(),'productTaxRates'=>\App\Models\TaxRate::where('is_active',true)->get()];
    }

    private function customerPayload(Customer $customer): array
    {
        return ['id'=>$customer->id,'name'=>$customer->name,'phone'=>$customer->phone,'address'=>$customer->delivery_address,'location'=>$customer->emirate,'area'=>$customer->area];
    }
}
