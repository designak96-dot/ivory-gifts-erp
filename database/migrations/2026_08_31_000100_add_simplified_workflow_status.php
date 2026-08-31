<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the new simplified workflow fields as ADDITIVE columns, kept
 * alongside the existing confirmation_status/design_status/
 * production_status/delivery_status fields rather than replacing them.
 *
 * Why: 13 existing files (Ivory AI, Notifications, Calendar, Saved
 * Filters, Production workflow, exports, imports) all read the old
 * granular values. Changing what those columns mean would silently
 * break every one of them. Instead, the new simplified fields are the
 * single source of truth for the new UI and its sync rules, and
 * SimpleWorkflowService mirrors relevant changes back into the old
 * fields — so everything that already depends on them keeps working
 * with an accurate picture, without being rewritten.
 *
 * Backfill for existing orders is best-effort from their current real
 * status combination — never destructive, and any order this can't
 * confidently classify simply defaults to Pending (never silently
 * marked further along than it actually is).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('simple_status')->default('pending')->after('delivery_status');
            $table->string('simple_confirmation')->default('not_confirmed')->after('simple_status');
            $table->string('simple_design')->default('need_designer')->after('simple_confirmation');
        });

        \DB::table('sales_orders')->where('delivery_status', 'delivered')->update([
            'simple_status' => 'delivered', 'simple_confirmation' => 'confirmed', 'simple_design' => 'designed',
        ]);
        \DB::table('sales_orders')->where('production_status', 'ready')->where('delivery_status', '!=', 'delivered')->update([
            'simple_status' => 'ready', 'simple_confirmation' => 'confirmed', 'simple_design' => 'designed',
        ]);
        \DB::table('sales_orders')->where('design_status', 'designed')->whereNotIn('simple_status', ['ready', 'delivered'])->update([
            'simple_design' => 'designed', 'simple_confirmation' => 'waiting_deposit',
        ]);
        \DB::table('sales_orders')->where('confirmation_status', 'confirmed')->update(['simple_confirmation' => 'confirmed']);
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
