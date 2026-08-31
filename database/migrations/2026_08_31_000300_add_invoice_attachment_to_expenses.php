<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. The existing proof_path/proof_original_name/proof_mime/
 * proof_size columns (already required — "Payment Proof / Slip") are
 * untouched. This adds a second, separate, OPTIONAL set of columns for
 * the "Expense Invoice / Bill" upload, so the two files are stored and
 * served independently and neither can overwrite the other. Every
 * historical expense row simply gets NULL for these new columns —
 * nothing about existing data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('invoice_path')->nullable()->after('proof_size');
            $table->string('invoice_original_name')->nullable()->after('invoice_path');
            $table->string('invoice_mime')->nullable()->after('invoice_original_name');
            $table->unsignedBigInteger('invoice_size')->nullable()->after('invoice_mime');
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
