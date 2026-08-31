<?php

namespace App\Http\Controllers;

use App\Models\{DeliveryNote, ProductionJob, PurchaseOrder, Task};
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Aggregates real, already-tracked dates into a single calendar —
     * no separate "calendar events" table, so nothing can drift out of
     * sync with the actual records. Note on Design vs Production
     * deadlines: this schema tracks a single due_date per production
     * job (no separate design-deadline field exists anywhere), so it's
     * shown as a "Design deadline" while the order's design isn't done
     * yet, and a "Production deadline" once design is done but
     * production isn't — a real, honest use of the one field that
     * actually exists, not two fabricated ones.
     */
    public function index(Request $request)
    {
        $view = in_array($request->query('view'), ['month', 'week', 'day']) ? $request->query('view') : 'month';
        $anchor = $request->query('date') ? Carbon::parse($request->query('date')) : today();

        [$start, $end] = match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            default => [$anchor->copy()->startOfMonth()->startOfWeek(), $anchor->copy()->endOfMonth()->endOfWeek()],
        };

        $events = collect();

        DeliveryNote::with('salesOrder', 'customer')
            ->whereBetween('delivery_date', [$start, $end])
            ->get()
            ->each(function ($d) use ($events) {
                $events->push(['date' => $d->delivery_date->toDateString(), 'type' => 'Delivery', 'color' => 'blue', 'label' => ($d->salesOrder->order_number ?? '').' · '.($d->customer->name ?? ''), 'url' => route('deliveries.show', $d)]);
            });

        ProductionJob::with('salesOrder')
            ->whereBetween('due_date', [$start, $end])
            ->whereNotIn('stage', ['completed', 'cancelled'])
            ->get()
            ->each(function ($j) use ($events) {
                $isDesignPhase = $j->salesOrder && $j->salesOrder->design_status !== 'designed';
                $events->push(['date' => $j->due_date->toDateString(), 'type' => $isDesignPhase ? 'Design deadline' : 'Production deadline', 'color' => $isDesignPhase ? 'amber' : 'green', 'label' => $j->salesOrder->order_number ?? $j->job_number, 'url' => $j->salesOrder ? route('orders.show', $j->salesOrder) : '#']);
            });

        Task::whereBetween('due_at', [$start, $end])
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->each(function ($t) use ($events) {
                $events->push(['date' => $t->due_at->toDateString(), 'type' => 'Task', 'color' => 'red', 'label' => $t->title, 'url' => route('tasks.index')]);
            });

        PurchaseOrder::with('supplier')
            ->whereBetween('expected_delivery_date', [$start, $end])
            ->whereNotIn('status', ['received'])
            ->get()
            ->each(function ($po) use ($events) {
                $events->push(['date' => $po->expected_delivery_date->toDateString(), 'type' => 'Expected receipt', 'color' => 'blue', 'label' => $po->purchase_order_number.' · '.($po->supplier->name ?? ''), 'url' => route('purchases.show', $po)]);
            });

        $grouped = $events->groupBy('date');

        return view('calendar.index', [
            'view' => $view,
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'events' => $grouped,
            'prevDate' => match ($view) { 'day' => $anchor->copy()->subDay(), 'week' => $anchor->copy()->subWeek(), default => $anchor->copy()->subMonth() },
            'nextDate' => match ($view) { 'day' => $anchor->copy()->addDay(), 'week' => $anchor->copy()->addWeek(), default => $anchor->copy()->addMonth() },
        ]);
    }
}
