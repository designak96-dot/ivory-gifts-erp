<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The seeder only runs on a fresh install — existing installations need
 * this sequence inserted directly so TRF-xxxxx numbering works
 * immediately after this update. */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('numbering_sequences')->updateOrInsert(
            ['document_type' => 'account_transfer'],
            ['prefix' => 'TRF-{YYYY}-', 'reset_policy' => 'yearly', 'padding' => 5, 'current_value' => 0, 'year' => $now->year, 'month' => $now->month, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
