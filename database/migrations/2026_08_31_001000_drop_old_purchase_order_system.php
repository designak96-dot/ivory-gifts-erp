<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the old formal Purchase Order system (Draft/Approved/Ordered/
 * Partially Received/Received) entirely — controller, routes, views,
 * and models were already removed from the codebase in this same
 * update. Confirmed via a full grep sweep of app/, database/, routes/,
 * and resources/views/ that no remaining code references
 * PurchaseOrder, PurchaseOrderItem, purchase_orders, or
 * purchase_order_items before this migration was written — the three
 * real dependencies that did exist (a calendar "expected receipt"
 * event, a CSV export, and a Sales Product price-history lookup) were
 * each individually fixed first. This is intentionally destructive per
 * an explicit request ("I do not need any old Purchase Order
 * history/data") — every other migration in this project stays
 * non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }

    public function down(): void
    {
        // Deliberately irreversible — the old PO system's code no longer
        // exists to make use of these tables even if recreated.
    }
};
