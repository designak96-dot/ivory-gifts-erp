<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance → Account Transfer: moves money between two Cash/Bank/Petty
 * Cash accounts (e.g. Cash → Bank deposit). Stored as its own record
 * (with its own number, optional reference, notes and proof) and, on
 * save, posted as a single balanced journal entry — credit the From
 * account, debit the To account — via the existing AccountingService.
 * Because both sides are always asset accounts, a transfer can never
 * touch income or expense and never affects profit; it only ever
 * relocates funds already on the books. Cash Reconciliation and Bank
 * Reconciliation both read straight off the account's real ledger
 * lines, so a posted transfer feeds both automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique(); // TRF-{YYYY}-xxxxx
            $table->foreignId('from_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('transfer_date')->index();
            $table->string('reference')->nullable(); // e.g. deposit slip / bank reference — also used by Bank Reconciliation matching
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
