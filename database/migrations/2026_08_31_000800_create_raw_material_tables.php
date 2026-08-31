<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A separate, standalone system — raw_materials is deliberately its own
 * table, not reusing `products` (which is Sales Products / finished
 * goods). Purely additive: no existing Product, PurchaseOrder, or
 * Expense table or data is touched. The old formal Purchase Order
 * workflow is left completely intact for anyone still using it; this is
 * a new, simpler, parallel path for everyday raw-material buying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('unit');
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('latest_cost', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('raw_material_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->date('purchase_date')->index();
            $table->decimal('quantity', 14, 3);
            $table->string('unit');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('payment_method')->index(); // cash | bank | unpaid
            $table->foreignId('bank_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('payment_reference')->nullable()->index(); // strongest bank-reconciliation match
            $table->text('notes')->nullable();
            // Supplier Invoice/Bill — reuses the same secure, private-disk
            // storage pattern as every other document in this app.
            $table->string('invoice_path')->nullable();
            $table->string('invoice_original_name')->nullable();
            $table->string('invoice_mime')->nullable();
            $table->unsignedBigInteger('invoice_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
