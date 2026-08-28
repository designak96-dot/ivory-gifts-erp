<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->boolean('is_legacy_delivery_import')->default(false)->after('is_very_urgent')->index();
        });

        DB::table('sync_logs')
            ->where('source', 'legacy_delivery')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                $numbers = $logs->pluck('external_reference')->filter()->unique()->values();
                if ($numbers->isNotEmpty()) {
                    DB::table('sales_orders')->whereIn('order_number', $numbers)->update([
                        'is_legacy_delivery_import' => true,
                    ]);
                }
            });

        DB::table('sales_orders')
            ->where('is_legacy_delivery_import', true)
            ->where('delivery_status', 'delivered')
            ->update([
                'confirmation_status' => 'confirmed',
                'design_status' => 'designed',
                'production_status' => 'completed',
                'priority' => 'normal',
                'is_very_urgent' => false,
            ]);

        DB::table('sales_orders')
            ->where('is_legacy_delivery_import', true)
            ->where('delivery_status', 'returned')
            ->update([
                'confirmation_status' => 'cancelled',
                'production_status' => 'completed',
                'priority' => 'normal',
                'is_very_urgent' => false,
            ]);
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['is_legacy_delivery_import']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('is_legacy_delivery_import');
        });
    }
};
