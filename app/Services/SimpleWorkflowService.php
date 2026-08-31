<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the new simplified Sales/Delivery workflow
 * (Status: Pending/Ready/Delivered/Canceled; Confirmation: Not
 * Confirmed/Waiting For Deposit/Confirmed; Design: Need Designer/
 * Designed), and the cascading rules between them. Whether a change
 * comes from the Sales Order screen or the Delivery screen, it always
 * goes through this one service — there is exactly one status per order,
 * read and written from the same three columns on sales_orders, so the
 * two screens can never show different values for the same order.
 *
 * Also mirrors relevant changes into the existing granular status
 * columns (confirmation_status, design_status, production_status,
 * delivery_status) so the 13 existing features that already depend on
 * those columns (Ivory AI, Notifications, Calendar, Saved Filters,
 * Production workflow, exports) continue to see an accurate picture
 * without needing to be rewritten.
 */
class SimpleWorkflowService
{
    public const STATUSES = ['pending', 'ready', 'delivered', 'canceled'];
    public const CONFIRMATIONS = ['not_confirmed', 'waiting_deposit', 'confirmed'];
    public const DESIGNS = ['need_designer', 'designed'];

    public function setStatus(SalesOrder $order, string $status): SalesOrder
    {
        abort_unless(in_array($status, self::STATUSES), 422, 'Invalid status.');

        DB::transaction(function () use ($order, $status) {
            $order->simple_status = $status;

            if ($status === 'ready' || $status === 'delivered') {
                // Rules 11 & 12: reaching Ready or Delivered always
                // implies Confirmed + Designed — you can't be ready or
                // delivered on an unconfirmed, undesigned order.
                $order->simple_confirmation = 'confirmed';
                $order->simple_design = 'designed';
            }
            // Rule 13: Canceled does not force confirmation/design to any
            // particular value — the UI displays them as a red "N/A"
            // regardless of the stored value, per the explicit
            // requirement not to mark a canceled order Confirmed or
            // Designed.

            $order->save();
            $this->mirrorToLegacyFields($order);
        });

        return $order->fresh();
    }

    public function setConfirmation(SalesOrder $order, string $confirmation): SalesOrder
    {
        abort_unless(in_array($confirmation, self::CONFIRMATIONS), 422, 'Invalid confirmation value.');

        DB::transaction(function () use ($order, $confirmation) {
            $order->simple_confirmation = $confirmation;

            if ($confirmation === 'confirmed') {
                // Rule 10: Confirmed implies Designed.
                $order->simple_design = 'designed';
            }

            $order->save();
            $this->mirrorToLegacyFields($order);
        });

        return $order->fresh();
    }

    public function setDesign(SalesOrder $order, string $design): SalesOrder
    {
        abort_unless(in_array($design, self::DESIGNS), 422, 'Invalid design value.');

        DB::transaction(function () use ($order, $design) {
            $order->simple_design = $design;

            if ($design === 'designed' && $order->simple_confirmation !== 'confirmed') {
                // Rule 9: Designed implies Waiting For Deposit — unless
                // confirmation is already Confirmed, which must never be
                // downgraded by a design change.
                $order->simple_confirmation = 'waiting_deposit';
            }

            $order->save();
            $this->mirrorToLegacyFields($order);
        });

        return $order->fresh();
    }

    /**
     * Best-effort mirror into the legacy granular columns so existing
     * features (Ivory AI, Notifications, Calendar, Saved Filters,
     * Production workflow) keep working accurately. Deliberately not
     * exhaustive of every legacy state — only the mappings needed for
     * those features to correctly recognize "delivered," "designed,"
     * "confirmed," and "in progress."
     */
    private function mirrorToLegacyFields(SalesOrder $order): void
    {
        $updates = [];

        if ($order->simple_design === 'designed') {
            $updates['design_status'] = 'designed';
        } elseif ($order->simple_status !== 'canceled') {
            $updates['design_status'] = 'need_design';
        }

        if ($order->simple_confirmation === 'confirmed') {
            $updates['confirmation_status'] = 'confirmed';
        } elseif ($order->simple_status !== 'canceled') {
            $updates['confirmation_status'] = 'waiting';
        }

        if ($order->simple_status === 'delivered') {
            $updates['delivery_status'] = 'delivered';
            $updates['production_status'] = 'completed';
        } elseif ($order->simple_status === 'ready') {
            $updates['production_status'] = 'ready';
            if ($order->delivery_status === 'delivered') $updates['delivery_status'] = 'not_scheduled';
        } elseif ($order->simple_status === 'pending' && $order->delivery_status === 'delivered') {
            // Shouldn't normally happen (reverting a delivered order),
            // but keep the legacy field honest if it does.
            $updates['delivery_status'] = 'not_scheduled';
        }

        if (!empty($updates)) {
            $order->forceFill($updates)->saveQuietly();
        }

        if ($order->simple_status === 'delivered') {
            \App\Models\DeliveryNote::where('sales_order_id', $order->id)->update(['status' => 'delivered', 'delivered_at' => now()]);
        }
    }
}
