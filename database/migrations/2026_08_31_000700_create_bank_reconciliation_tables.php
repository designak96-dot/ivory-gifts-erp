<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('statement_month'); // first day of the statement month
            $table->decimal('opening_balance', 14, 2)->nullable();
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->decimal('total_credits', 14, 2)->default(0);
            $table->decimal('total_debits', 14, 2)->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_in_count')->default(0);
            $table->unsignedInteger('unmatched_out_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0); // ERP transactions not found on the statement
            $table->string('status')->default('needs_review')->index(); // reconciled | needs_review
            // The uploaded statement itself — never publicly reachable.
            // Served only through an authenticated, permission-checked
            // controller route, same pattern as every other proof/document
            // file in this app.
            $table->string('statement_file_path')->nullable();
            $table->string('statement_original_name')->nullable();
            $table->string('statement_mime')->nullable();
            $table->unsignedBigInteger('statement_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->date('txn_date');
            $table->string('description')->nullable();
            $table->string('bank_reference')->nullable()->index();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('amount', 14, 2); // signed: positive = credit/in, negative = debit/out
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('match_status')->default('missing_in_erp')->index(); // matched | possible_match | missing_in_erp
            $table->string('matched_type')->nullable(); // payment | expense
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
