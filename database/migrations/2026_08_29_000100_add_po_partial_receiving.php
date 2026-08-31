<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only — adds qty_received (defaulting to 0, backfilled below
 * for existing 'received' POs so they show as fully received rather than
 * silently reverting to a false 0/x display) without touching any
 * existing stored totals or the status column itself (still a plain
 * string, so no data migration risk there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('qty_received', 12, 3)->default(0)->after('qty');
        });

        // Backfill: any PO already marked 'received' under the old
        // binary workflow gets its items marked fully received, so the
        // new partial-receiving UI reflects reality instead of showing
        // 0 received on an order that's actually complete.
        \DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.status', 'received')
            ->update(['purchase_order_items.qty_received' => \DB::raw('purchase_order_items.qty')]);
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
