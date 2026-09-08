<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery Finance v2: real payment history (not a single amount_paid
 * field) for courier bills and driver settlements, each with an
 * idempotency key to stop duplicate submissions; petrol/vehicle costs
 * now route through the existing Expenses module directly (not a
 * parallel VehicleExpense-only flow); a vehicle requirement for
 * own-company delivery completion. Purely additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('courier_supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('payroll_period')->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('vehicle_id')->constrained('users')->nullOnDelete();
            $table->string('paid_by')->nullable()->after('driver_id'); // company | driver — required whenever vehicle_id is set, never guessed
            $table->string('reimbursement_status')->nullable()->after('paid_by')->index(); // null | pending | reimbursed
            $table->date('delivery_day')->nullable()->after('reimbursement_status')->index(); // the day of deliveries this cost relates to, for daily driver report linkage
        });

        // General-purpose allocation of a real Expense across deliveries —
        // analytical only, never creates a second accounting entry. Used by
        // petrol/maintenance/repairs entered through the Expenses module.
        Schema::create('expense_delivery_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();
            $table->unique(['expense_id', 'delivery_note_id']);
        });

        // Real payment history — replaces treating amount_paid as a single
        // incrementable number with no record of each individual payment.
        Schema::create('courier_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('proof_path');
            $table->string('proof_original_name')->nullable();
            $table->string('idempotency_key')->unique(); // the actual, durable duplicate-submission guard
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('driver_settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_settlement_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            // A partial payment is deterministically split between the two
            // accounting categories it settles — fees first, then allowance —
            // and this split is what actually gets posted/updated in Expenses.
            $table->decimal('fee_portion', 12, 2)->default(0);
            $table->decimal('allowance_portion', 12, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('proof_path');
            $table->string('proof_original_name')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
