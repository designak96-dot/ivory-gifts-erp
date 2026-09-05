<?php

namespace App\Http\Controllers;

use App\Models\{DeliveryNote, SalesOrderStatusHistory, User};
use App\Services\DeliverySchedulingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryController extends Controller
{
    private const ACTIVE_STATUSES = ['pending', 'out_for_delivery', 'partial', 'failed'];
    private const LOCATIONS = ['Abu Dhabi','Dubai','Sharjah','Ajman','Umm Al Quwain','Ras Al Khaimah','Fujairah','Western Region','Personal Pickup','Saudi Arabia','Oman','Qatar','Kuwait','Bahrain','Other GCC'];

    public function index(Request $request, DeliverySchedulingService $scheduler)
    {
        return view('deliveries.index', $this->pageData($request, $scheduler));
    }

    public function live(Request $request, DeliverySchedulingService $scheduler)
    {
        $data = $this->pageData($request, $scheduler);

        return response()->json([
            'version' => $data['version'],
            'html' => view('deliveries._live', $data)->render(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function show(DeliveryNote $delivery, DeliverySchedulingService $scheduler, \App\Services\DeliveryFinanceService $financeService)
    {
        $delivery->load('customer', 'salesOrder.items', 'driver', 'courierSupplier', 'courierBill', 'driverSettlement');
        $drivers = $this->drivers();
        $suggestedDate = $scheduler->nextAvailableDate(
            $delivery->delivery_date ?: today(),
            $delivery->id
        );

        $isDriverOnly = $this->driverOnly();
        $canViewFinance = auth()->user()->hasPermission('deliveries.view.finance');
        $profitLoss = $canViewFinance && $delivery->delivery_type ? $financeService->directProfitLoss($delivery) : null;
        $fullyAllocated = $canViewFinance && $delivery->delivery_type ? $financeService->fullyAllocatedProfitLoss($delivery) : null;

        return view('deliveries.show', compact('delivery', 'drivers', 'suggestedDate', 'isDriverOnly', 'canViewFinance', 'profitLoss', 'fullyAllocated'));
    }

    public function updateFinance(Request $request, DeliveryNote $delivery)
    {
        abort_unless(auth()->user()->hasPermission('deliveries.manage'), 403);
        $data = $request->validate([
            'delivery_type' => 'required|in:own_company,domestic_outside_courier,international_courier,customer_pickup',
            'customer_delivery_charge' => 'nullable|numeric|min:0', 'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0', 'amount_collected' => 'nullable|numeric|min:0',
            'charge_override_reason' => 'nullable|string|max:255',
        ]);
        // Overriding the charge/cost for one delivery requires the specific permission and the reason is kept for audit history.
        if ($request->filled('charge_override_reason')) {
            abort_unless(auth()->user()->hasPermission('deliveries.edit.charge') || auth()->user()->hasPermission('deliveries.edit.cost'), 403);
        }
        $delivery->update($data);
        return back()->with('success', 'Delivery finance details updated.');
    }

    public function nextAvailable(Request $request, DeliverySchedulingService $scheduler)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'delivery_id' => 'nullable|exists:delivery_notes,id',
        ]);
        $date = $scheduler->nextAvailableDate(
            $data['from'] ?? today(),
            isset($data['delivery_id']) ? (int) $data['delivery_id'] : null
        );

        return response()->json([
            'date' => $date->toDateString(),
            'label' => $date->format('D, d M Y'),
            'limit' => $scheduler->dailyLimit(),
        ]);
    }

    /**
     * The new simplified Confirmation/Design/Status fields — read and
     * written on the LINKED Sales Order via SimpleWorkflowService, so
     * Deliveries and Sales can never show different values for the same
     * order. Kept entirely separate from update() above, which still
     * drives the delivery's own physical logistics (POD photo, driver
     * assignment, capacity scheduling) — that workflow is unchanged.
     */
    public function updateOrderWorkflow(Request $request, DeliveryNote $delivery, \App\Services\SimpleWorkflowService $workflow)
    {
        $data = $request->validate([
            'field' => 'required|in:status,confirmation,design',
            'value' => 'required|string',
        ]);
        $order = $delivery->salesOrder;
        match ($data['field']) {
            'status' => $workflow->setStatus($order, $data['value']),
            'confirmation' => $workflow->setConfirmation($order, $data['value']),
            'design' => $workflow->setDesign($order, $data['value']),
        };
        $order->refresh();
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => $order->simple_status, 'confirmation' => $order->simple_confirmation, 'design' => $order->simple_design]);
        }
        return back()->with('success', 'Order status updated.');
    }

    public function update(Request $request, DeliveryNote $delivery, DeliverySchedulingService $scheduler)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,out_for_delivery,delivered,partial,failed,returned',
            'driver_id' => 'nullable|exists:users,id',
            'delivery_date' => 'nullable|date',
            'package_size' => 'required|in:standard,large,pickup',
            'delivery_charge' => 'nullable|numeric|min:0|max:99999',
            'location_url' => 'nullable|url|max:2048',
            'recipient_name' => 'nullable|string|max:190',
            'failure_reason' => 'nullable|string|max:2000',
            'delivery_notes' => 'nullable|string|max:5000',
            'pod_photo' => 'nullable|image|max:5120',
            'signature' => 'nullable|image|max:2048',
        ]);

        if ($this->driverOnly()) {
            unset($data['driver_id'], $data['delivery_date'], $data['package_size'], $data['delivery_charge']);
        }

        try {
            if (!empty($data['delivery_date']) && $delivery->delivery_date?->toDateString() !== $data['delivery_date']) {
                $scheduler->ensureAvailable($data['delivery_date'], $delivery->id);
            }
            if (!$this->driverOnly()) {
                $calculatedCharge = $scheduler->charge(
                    $delivery->salesOrder->emirate ?: $delivery->customer->emirate,
                    $data['package_size'] ?? $delivery->package_size
                );
                $packageChanged = ($data['package_size'] ?? $delivery->package_size) !== $delivery->package_size;
                $chargeWasUnchanged = isset($data['delivery_charge'])
                    && (float) $data['delivery_charge'] === (float) $delivery->delivery_charge;
                if (!isset($data['delivery_charge']) || ($packageChanged && $chargeWasUnchanged)) {
                    $data['delivery_charge'] = $calculatedCharge;
                }
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['delivery_date' => $exception->getMessage()])->withInput();
        }

        if ($request->hasFile('pod_photo')) {
            $data['pod_photo_path'] = $request->file('pod_photo')->store('pod', 'public');
        }
        $proofJustUploaded = $request->hasFile('pod_photo');
        if ($request->hasFile('signature')) {
            $data['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }
        unset($data['pod_photo'], $data['signature']);

        if ($data['status'] === 'delivered' && empty($data['pod_photo_path']) && !$delivery->pod_photo_path) {
            return back()->withErrors(['pod_photo' => 'A proof-of-delivery photo is required.'])->withInput();
        }
        if ($data['status'] === 'failed' && empty($data['failure_reason'])) {
            return back()->withErrors(['failure_reason' => 'Please record why the delivery failed.'])->withInput();
        }

        DB::transaction(function () use ($delivery, $data, $proofJustUploaded) {
            $oldStatus = $delivery->status;
            $oldDriverId = $delivery->driver_id;
            $oldDeliveryDate = $delivery->delivery_date;
            $data['last_updated_by'] = auth()->id();
            $data['delivered_at'] = $data['status'] === 'delivered' ? ($delivery->delivered_at ?: now()) : null;
            if ($data['status'] === 'failed' && $oldStatus !== 'failed') {
                $data['attempt_count'] = $delivery->attempt_count + 1;
            }
            $delivery->update($data);
            if ($proofJustUploaded) {
                SalesOrderStatusHistory::create(['sales_order_id' => $delivery->sales_order_id, 'field' => 'proof', 'old_value' => null, 'new_value' => 'Proof-of-delivery photo uploaded', 'changed_by' => auth()->id()]);
            }

            // Delivery Finance automation: driver fee + daily allowance are applied
            // the moment an own-company delivery is genuinely marked delivered —
            // not left for someone to remember to trigger separately. Recalculates
            // for both the old and new driver/date so a status reversal or driver
            // reassignment keeps the daily-allowance split correct either way.
            if ($delivery->delivery_type === 'own_company') {
                $financeService = app(\App\Services\DeliveryFinanceService::class);
                if ($delivery->status === 'delivered' && $delivery->driver_id) {
                    $financeService->completeOwnDelivery($delivery, $delivery->delivery_date ?? now());
                } elseif ($oldStatus === 'delivered' && $oldDriverId) {
                    $delivery->update(['driver_fee' => 0]);
                    $financeService->recalculateDailyAllowanceForDriver($oldDriverId, $oldDeliveryDate ?? now());
                }
                if ($oldDriverId && $oldDriverId !== $delivery->driver_id) {
                    $financeService->recalculateDailyAllowanceForDriver($oldDriverId, $oldDeliveryDate ?? now());
                }
            }

            $orderStatus = match ($delivery->status) {
                'out_for_delivery' => 'out_for_delivery',
                'delivered' => 'delivered',
                'failed' => 'failed',
                'returned' => 'returned',
                default => 'scheduled',
            };
            $orderChanges = [
                'delivery_status' => $orderStatus,
                'driver_id' => $delivery->driver_id,
                'delivery_date' => $delivery->delivery_date,
            ];
            foreach ($orderChanges as $field => $value) {
                $old = $delivery->salesOrder->{$field};
                if ((string) $old !== (string) $value) {
                    SalesOrderStatusHistory::create([
                        'sales_order_id' => $delivery->sales_order_id,
                        'field' => $field,
                        'old_value' => $old,
                        'new_value' => $value ?? '',
                        'changed_by' => auth()->id(),
                    ]);
                }
            }
            $delivery->salesOrder->update($orderChanges);
        });

        return back()->with('success', 'Delivery updated. Staff schedules will refresh automatically.');
    }

    public function report(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?: today()->toDateString();
        $base = $this->visibleQuery()->whereBetween('delivery_date', [$from, $to]);
        $total = (clone $base)->count();
        $delivered = (clone $base)->where('status', 'delivered')->count();
        $failed = (clone $base)->whereIn('status', ['failed', 'returned'])->count();
        $charges = (clone $base)->sum('delivery_charge');
        $byDriver = (clone $base)->with('driver')->get()->groupBy(fn ($delivery) => $delivery->driver?->name ?: 'Unassigned');
        $byEmirate = (clone $base)->with('salesOrder:id,emirate')->get()->groupBy(fn ($delivery) => $delivery->salesOrder->emirate ?: 'Unspecified');

        return view('deliveries.report', compact('from', 'to', 'total', 'delivered', 'failed', 'charges', 'byDriver', 'byEmirate'));
    }

    public function quickUpdate(Request $request, DeliveryNote $delivery)
    {
        $data = $request->validate([
            'confirmation_status' => 'sometimes|in:waiting,waiting_for_deposit,confirmed,cancelled',
            'design_status' => 'sometimes|in:need_design,designing,waiting_customer,designed',
            'driver_id' => 'sometimes|nullable|exists:users,id',
            'status' => 'sometimes|in:pending,out_for_delivery,delivered,partial,failed,returned',
        ]);
        DB::transaction(function () use ($delivery, $data) {
            $orderChanges = [];
            foreach (['confirmation_status','design_status'] as $field) if (array_key_exists($field, $data)) $orderChanges[$field] = $data[$field];
            if (array_key_exists('driver_id', $data)) {
                $delivery->driver_id = $data['driver_id'];
                $orderChanges['driver_id'] = $data['driver_id'];
            }
            if (array_key_exists('status', $data)) {
                $delivery->status = $data['status'];
                $delivery->delivered_at = $data['status'] === 'delivered' ? ($delivery->delivered_at ?: now()) : null;
                $orderChanges['delivery_status'] = match ($data['status']) {
                    'out_for_delivery' => 'out_for_delivery', 'delivered' => 'delivered',
                    'failed' => 'failed', 'returned' => 'returned', default => 'scheduled',
                };
            }
            foreach ($orderChanges as $field => $value) {
                $old = $delivery->salesOrder->{$field};
                if ((string)$old !== (string)$value) SalesOrderStatusHistory::create(['sales_order_id'=>$delivery->sales_order_id,'field'=>$field,'old_value'=>$old,'new_value'=>$value??'','changed_by'=>auth()->id()]);
            }
            $delivery->last_updated_by = auth()->id();
            $delivery->save();
            $delivery->salesOrder->update($orderChanges);
        });
        return back();
    }

    private function pageData(Request $request, DeliverySchedulingService $scheduler): array
    {
        $query = $this->visibleQuery()->with('customer', 'salesOrder', 'driver');
        $this->applyFilters($query, $request);
        $deliveries = $query
            ->orderByRaw("CASE WHEN status='out_for_delivery' THEN 0 WHEN delivery_date < ? AND status NOT IN ('delivered','returned') THEN 1 ELSE 2 END", [today()->toDateString()])
            ->orderBy('delivery_date')
            ->orderByDesc('sales_order_id')
            ->paginate(50)
            ->withQueryString();

        $today = today()->toDateString();
        $monthValue = preg_match('/^\d{4}-\d{2}$/', (string) $request->month) ? $request->month : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
        $statsQuery = $this->visibleQuery();
        $stats = [
            'month_total' => (clone $statsQuery)->whereBetween('delivery_date', [$month, $month->copy()->endOfMonth()])->count(),
            'overdue' => (clone $statsQuery)->whereDate('delivery_date', '<', $today)->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'waiting_deposit' => (clone $statsQuery)->whereHas('salesOrder', fn($q)=>$q->where('simple_confirmation','waiting_deposit'))->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'need_design' => (clone $statsQuery)->whereDate('delivery_date','<=',today()->addDays(10))->whereHas('salesOrder', fn($q)=>$q->where('simple_design','need_designer'))->whereIn('status', self::ACTIVE_STATUSES)->count(),
        ];

        // Real list content for the three compact dropdowns — not just
        // counts. Each query mirrors its corresponding stat above exactly,
        // so the number on the button and the rows inside it can never
        // disagree.
        $quickLists = [
            'overdue' => (clone $statsQuery)->with('salesOrder', 'customer')->whereDate('delivery_date', '<', $today)->whereIn('status', self::ACTIVE_STATUSES)->orderBy('delivery_date')->limit(30)->get(),
            'waiting_deposit' => (clone $statsQuery)->with('salesOrder', 'customer')->whereHas('salesOrder', fn($q)=>$q->where('simple_confirmation','waiting_deposit'))->whereIn('status', self::ACTIVE_STATUSES)->orderBy('delivery_date')->limit(30)->get(),
            'need_design' => (clone $statsQuery)->with('salesOrder', 'customer')->whereDate('delivery_date','<=',today()->addDays(10))->whereHas('salesOrder', fn($q)=>$q->where('simple_design','need_designer'))->whereIn('status', self::ACTIVE_STATUSES)->orderBy('delivery_date')->limit(30)->get(),
        ];

        $calendarCounts = $this->visibleQuery()
            ->selectRaw('delivery_date, COUNT(*) as total')
            ->whereBetween('delivery_date', [$month, $month->copy()->endOfMonth()])
            ->whereNotIn('status', ['delivered', 'returned'])
            ->groupBy('delivery_date')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->delivery_date)->toDateString() => (int) $row->total]);
        $calendarDays = collect(range(1, $month->daysInMonth))->map(function ($day) use ($month, $calendarCounts) {
            $date = $month->copy()->day($day);
            return [
                'date' => $date,
                'count' => (int) ($calendarCounts[$date->toDateString()] ?? 0),
            ];
        });

        return [
            'deliveries' => $deliveries,
            'stats' => $stats,
            'quickLists' => $quickLists,
            'drivers' => $this->drivers(),
            'calendarDays' => $calendarDays,
            'calendarMonth' => $month,
            'dailyLimit' => $scheduler->dailyLimit(),
            'locations' => self::LOCATIONS,
            'version' => ($latest = DeliveryNote::max('updated_at')) ? Carbon::parse($latest)->format('Y-m-d H:i:s.u') : '',
            'isDriverOnly' => $this->driverOnly(),
        ];
    }

    private function visibleQuery(): Builder
    {
        return DeliveryNote::query()
            ->whereHas('salesOrder', fn ($q) => $q->whereNotIn('simple_status', ['delivered', 'canceled']))
            ->when($this->driverOnly(), fn ($query) => $query->where('driver_id', auth()->id()));
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('delivery_note_number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', fn($order) => $order->where('order_number','like',"%{$search}%")->orWhere('delivery_address','like',"%{$search}%")->orWhere('customer_phone','like',"%{$search}%"))
                    ->orWhereHas('customer', fn($customer) => $customer->where('name','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhere('area','like',"%{$search}%"));
            });
        }
        if ($request->filled('date')) {
            $query->whereDate('delivery_date', $request->date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('driver') && !$this->driverOnly()) {
            $query->where('driver_id', $request->integer('driver'));
        }
        if ($request->filled('location')) {
            $query->whereHas('salesOrder', fn ($order) => $order->where('emirate', $request->location));
        }
        if ($request->scope === 'today') {
            $query->whereDate('delivery_date', today());
        } elseif ($request->scope === 'overdue') {
            $query->whereDate('delivery_date', '<', today())->whereIn('status', self::ACTIVE_STATUSES);
        } elseif ($request->scope === 'upcoming') {
            $query->whereBetween('delivery_date', [today()->addDay(), today()->addDays(7)])->whereIn('status', self::ACTIVE_STATUSES);
        } elseif ($request->scope === 'unassigned') {
            $query->whereNull('driver_id')->whereIn('status', self::ACTIVE_STATUSES);
        } elseif ($request->scope !== 'all' && !$request->filled('status')) {
            $query->whereIn('status', self::ACTIVE_STATUSES);
        }
    }

    private function drivers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['driver', 'delivery_coordinator']))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    private function driverOnly(): bool
    {
        return auth()->user()->hasRole('driver') && !auth()->user()->hasRole('delivery_coordinator') && !auth()->user()->hasRole('owner');
    }
}
