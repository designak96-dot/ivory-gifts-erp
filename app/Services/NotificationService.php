<?php

namespace App\Services;

use App\Models\{DeliveryNote, Invoice, SalesOrder};

/**
 * Computed live from real data every time it's called — no stored
 * notifications table, no background job to keep in sync, no staleness
 * risk. Every alert is a genuine current condition in the database, not
 * a historical log entry.
 */
class NotificationService
{
    public function all(): array
    {
        $notifications = [];

        foreach ($this->overdueDeliveries() as $d) {
            $notifications[] = ['severity' => 'red', 'title' => 'Overdue delivery', 'message' => "{$d->salesOrder->order_number} · {$d->customer->name}", 'url' => route('deliveries.show', $d)];
        }

        foreach ($this->overdueInvoices() as $i) {
            $notifications[] = ['severity' => 'red', 'title' => 'Overdue invoice', 'message' => "{$i->invoice_number} · AED ".number_format($i->outstanding_amount, 2)." overdue", 'url' => route('invoices.show', $i)];
        }

        foreach ($this->designWaitingTooLong() as $o) {
            $notifications[] = ['severity' => 'amber', 'title' => 'Design waiting too long', 'message' => "Order {$o->order_number} — design not started in ".$o->order_date->diffInDays(today())." days", 'url' => route('orders.show', $o)];
        }

        foreach ($this->productionDelayed() as $o) {
            $notifications[] = ['severity' => 'amber', 'title' => 'Production delay', 'message' => "Order {$o->order_number} — production behind schedule", 'url' => route('orders.show', $o)];
        }

        foreach (app(StockIntelligenceService::class)->lowStock() as $row) {
            $notifications[] = ['severity' => 'amber', 'title' => 'Low stock', 'message' => "{$row['product']->name_en} ({$row['product']->sku}) — {$row['on_hand']} left", 'url' => route('inventory.index')];
        }

        foreach ($this->dueSoonNotReady() as $o) {
            $notifications[] = ['severity' => 'red', 'title' => 'Due soon, not ready', 'message' => "Order {$o->order_number} — delivery due ".$o->delivery_date->diffForHumans().", not yet ready", 'url' => route('orders.show', $o)];
        }

        foreach ($this->dueTaskReminders() as $t) {
            $notifications[] = ['severity' => $t->is_overdue ? 'red' : 'amber', 'title' => $t->is_overdue ? 'Task overdue' : 'Task reminder', 'message' => $t->title.' — due '.$t->due_at->diffForHumans(), 'url' => route('tasks.index')];
        }

        return $notifications;
    }

    /** Tasks with reminders enabled that are overdue or due within the next 24 hours. */
    private function dueTaskReminders()
    {
        return \App\Models\Task::where('reminder_enabled', true)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDay())
            ->orderBy('due_at')
            ->limit(10)
            ->get();
    }

    private function overdueDeliveries()
    {
        return DeliveryNote::with('salesOrder', 'customer')
            ->whereNotNull('delivery_date')
            ->where('delivery_date', '<', today())
            ->where('status', '!=', 'delivered')
            ->limit(10)->get();
    }

    private function overdueInvoices()
    {
        return Invoice::where('outstanding_amount', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->limit(10)->get();
    }

    /** Orders where design hasn't even started, more than 3 days after the order was placed. */
    private function designWaitingTooLong()
    {
        return SalesOrder::where('design_status', 'need_design')
            ->where('order_date', '<=', today()->subDays(3))
            ->limit(10)->get();
    }

    /** Orders whose production is behind: still not complete, with the delivery date within 2 days or already passed. */
    private function productionDelayed()
    {
        return SalesOrder::whereNotIn('production_status', ['completed', 'ready'])
            ->whereNotNull('delivery_date')
            ->where('delivery_date', '<=', today()->addDays(2))
            ->limit(10)->get();
    }

    /** Orders due within 2 days where neither design nor production has reached a ready/complete state. */
    private function dueSoonNotReady()
    {
        return SalesOrder::whereNotNull('delivery_date')
            ->whereBetween('delivery_date', [today(), today()->addDays(2)])
            ->where('delivery_status', '!=', 'delivered')
            ->where(fn ($q) => $q->whereIn('design_status', ['need_design', 'designing', 'waiting_customer'])->orWhereNotIn('production_status', ['completed', 'ready']))
            ->limit(10)->get();
    }
}
