<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment proof — nullable so existing historical payments remain valid.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference_number');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->string('proof_mime')->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime');
        });

        // Expense proof — nullable so existing historical expenses remain valid.
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->string('proof_mime')->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime');
        });

        // Sales order numbering upgrade: manual reference + monthly sequence,
        // kept as separate columns per the spec rather than only a formatted
        // string, so the relational parts remain queryable/lockable.
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('manual_reference', 20)->nullable()->after('order_number');
            $table->unsignedInteger('monthly_sequence')->nullable()->after('manual_reference');
        });

        // A single "last used month" counter can't correctly resume a prior
        // month's sequence if orders are entered out of chronological order
        // (explicitly required: "an older order created later using a
        // January order date... generate the next unused January sequence
        // safely"). One row per (year, month) instead, so every month's
        // counter is genuinely independent no matter what order they're
        // touched in.
        Schema::create('sales_order_monthly_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_monthly_counters');
        Schema::table('sales_orders', fn (Blueprint $t) => $t->dropColumn(['manual_reference', 'monthly_sequence']));
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['proof_path', 'proof_original_name', 'proof_mime', 'proof_size']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['proof_path', 'proof_original_name', 'proof_mime', 'proof_size']));
    }
};
