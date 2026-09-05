<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery Finance & Profitability — extends the EXISTING delivery_notes
 * table (never creates a parallel delivery record) with financial
 * tracking, and adds Courier Bills (one bill, many delivery lines),
 * Driver Settlements (with the daily AED 5 allowance never duplicated
 * per driver+date), and shared Vehicle Expenses. Every automatically
 * created Expense reuses the existing expenses.source_type/source_id
 * idempotency pattern already in place from the Staff/Payroll module. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_type')->nullable()->after('name')->index(); // e.g. delivery_courier
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('delivery_type')->nullable()->after('status')->index(); // own_company | domestic_outside_courier | international_courier | customer_pickup
            $table->foreignId('courier_supplier_id')->nullable()->after('driver_id')->constrained('suppliers')->nullOnDelete();
            // Finance
            $table->decimal('customer_delivery_charge', 12, 2)->default(0);
            $table->decimal('amount_collected', 12, 2)->default(0);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->nullable(); // null until real cost is known — estimated vs final profit hinges on this
            $table->decimal('driver_fee', 12, 2)->default(0);
            $table->decimal('allocated_phone_allowance', 12, 2)->default(0);
            $table->decimal('allocated_petrol_cost', 12, 2)->default(0);
            $table->decimal('allocated_maintenance_cost', 12, 2)->default(0);
            $table->boolean('driver_fee_manually_approved')->default(false); // cancelled/failed deliveries only earn a fee with explicit Owner/Admin override
            $table->string('driver_fee_override_reason')->nullable();
            // International-specific (nullable — only populated for international_courier deliveries)
            $table->string('destination_country')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('intl_service_type')->nullable();
            $table->integer('box_count')->nullable();
            $table->string('box_dimensions')->nullable();
            $table->decimal('actual_weight', 10, 2)->nullable();
            $table->decimal('volumetric_weight', 10, 2)->nullable();
            $table->decimal('chargeable_weight', 10, 2)->nullable();
            $table->string('awb_number')->nullable();
            $table->date('shipment_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            // Currency (for international courier bills in another currency)
            $table->string('cost_currency', 3)->nullable();
            $table->decimal('cost_original_amount', 12, 2)->nullable();
            $table->decimal('cost_exchange_rate', 12, 6)->nullable();
            $table->date('cost_exchange_rate_date')->nullable();
            // Links
            $table->foreignId('courier_bill_id')->nullable()->after('courier_supplier_id')->index();
            $table->foreignId('driver_settlement_id')->nullable()->after('courier_bill_id')->index();
            $table->boolean('charge_included_in_invoice')->default(false); // true => do not post a second Delivery Income entry
        });

        Schema::create('courier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_invoice_number')->nullable();
            $table->date('bill_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 3)->default('AED');
            $table->decimal('amount_ex_tax', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->decimal('aed_equivalent', 12, 2)->nullable();
            $table->string('status')->default('draft')->index(); // draft|received|approved|partially_paid|paid|cancelled
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('courier_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_note_id')->constrained()->restrictOnDelete();
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_billed_cost', 12, 2)->default(0);
            $table->timestamps();
            $table->unique('delivery_note_id'); // a delivery can only ever be billed once — the core anti-duplicate rule for section 21
        });

        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('delivery_fee_total', 12, 2)->default(0);
            $table->decimal('allowance_total', 12, 2)->default(0);
            $table->decimal('total_payable', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('status')->default('draft')->index(); // draft|approved|partially_paid|paid|cancelled
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('driver_daily_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->date('allowance_date');
            $table->decimal('amount', 12, 2)->default(5);
            $table->foreignId('driver_settlement_id')->nullable()->constrained('driver_settlements')->nullOnDelete();
            $table->timestamps();
            $table->unique(['driver_id', 'allowance_date']); // the hard guarantee from spec section 8 — never more than one AED 5/driver/day
        });

        Schema::create('delivery_finance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->index(); // domestic_customer_charge | domestic_courier_estimated_cost | own_driver_fee | own_driver_daily_allowance
            $table->decimal('value', 12, 2);
            $table->date('effective_date')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plate_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_type')->index(); // petrol|maintenance|repair|tyres|registration|insurance|car_wash|parking|toll|other
            $table->date('expense_date');
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount_ex_tax', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('proof_path')->nullable();
            $table->unsignedInteger('odometer_reading')->nullable();
            $table->string('maintenance_type')->nullable();
            $table->string('description')->nullable();
            $table->date('next_service_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Analytical-only allocation of a real vehicle expense across deliveries —
        // never creates a second accounting Expense (spec section 12's core rule).
        Schema::create('vehicle_expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
