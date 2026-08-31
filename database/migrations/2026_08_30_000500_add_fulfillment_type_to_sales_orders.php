<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. Defaults every existing order to 'delivery' — the
 * behavior this app already had for every order before this field
 * existed — so nothing about historical orders changes in meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('fulfillment_type')->default('delivery')->after('delivery_address');
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
