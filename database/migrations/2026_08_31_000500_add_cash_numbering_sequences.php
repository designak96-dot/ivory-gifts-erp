<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The seeder only runs on a fresh install — existing installations need
 * these new sequences inserted directly so CR-xxxxx / CP-xxxxx
 * generation works immediately after this update, without requiring a
 * re-seed that could disturb existing sequence counters.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([['cash_receipt', 'CR-'], ['cash_payment', 'CP-']] as [$type, $prefix]) {
            DB::table('numbering_sequences')->updateOrInsert(
                ['document_type' => $type],
                ['prefix' => $prefix, 'reset_policy' => 'none', 'padding' => 5, 'current_value' => 0, 'year' => $now->year, 'month' => $now->month, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
