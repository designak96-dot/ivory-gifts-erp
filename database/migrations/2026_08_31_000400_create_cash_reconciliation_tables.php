<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('reconciliation_date')->index();
            $table->decimal('opening_cash', 14, 2);
            $table->decimal('cash_in', 14, 2);
            $table->decimal('cash_out', 14, 2);
            $table->decimal('expected_cash', 14, 2);
            $table->decimal('physical_cash_count', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->string('status')->default('draft')->index(); // draft | reviewed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // CR-xxxxx (in) or CP-xxxxx (out)
            $table->foreignId('cash_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('type')->default('adjustment')->index(); // supplier_payment, refund, petty_cash, adjustment
            $table->string('direction')->index(); // in | out
            $table->decimal('amount', 14, 2);
            $table->date('adjustment_date')->index();
            $table->string('reason');
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->foreignId('cash_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
