<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal-only comments on Sales Orders. This table is never referenced
 * by any customer-facing document view (invoices.show, quotations.show,
 * deliveries.show) — it's only rendered inside orders/show.blade.php,
 * which is an authenticated staff-only page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
