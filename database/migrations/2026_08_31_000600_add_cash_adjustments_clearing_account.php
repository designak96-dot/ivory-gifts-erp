<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A single clearing account for cash adjustments (supplier cash payments,
 * refunds, petty cash, and generic adjustments) that don't cleanly map to
 * one existing account — keeps the cash account's ledger balance accurate
 * without over-engineering a per-type mapping. An accountant can
 * reclassify individual entries later if needed. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('chart_of_accounts')->updateOrInsert(
            ['code' => '3900'],
            ['name' => 'Cash Reconciliation Clearing', 'type' => 'equity', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
