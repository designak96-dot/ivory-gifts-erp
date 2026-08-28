<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_phone', 30)->nullable()->after('customer_id');
        });

        DB::table('sales_orders')->whereNull('customer_phone')->orderBy('id')->chunkById(250, function ($orders) {
            $phones = DB::table('customers')->whereIn('id', $orders->pluck('customer_id'))->pluck('phone', 'id');
            foreach ($orders as $order) {
                DB::table('sales_orders')->where('id', $order->id)->update(['customer_phone' => $phones[$order->customer_id] ?? null]);
            }
        });

        DB::table('sales_orders')
            ->whereNotNull('delivery_date')
            ->where('is_legacy_delivery_import', false)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('delivery_notes')
                    ->whereColumn('delivery_notes.sales_order_id', 'sales_orders.id');
            })
            ->orderBy('id')
            ->chunkById(250, function ($orders) {
                foreach ($orders as $order) {
                    $status = match ($order->delivery_status) {
                        'out_for_delivery' => 'out_for_delivery',
                        'delivered' => 'delivered',
                        'failed' => 'failed',
                        'returned' => 'returned',
                        default => 'pending',
                    };
                    DB::table('delivery_notes')->insert([
                        'delivery_note_number' => 'AUTO-DN-'.$order->id,
                        'sales_order_id' => $order->id,
                        'customer_id' => $order->customer_id,
                        'driver_id' => $order->driver_id,
                        'delivery_date' => $order->delivery_date,
                        'status' => $status,
                        'delivered_at' => $status === 'delivered' ? $order->updated_at : null,
                        'package_size' => 'standard',
                        'delivery_charge' => 0,
                        'delivery_notes' => 'Automatically added from the existing sales order schedule.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('customer_phone');
        });
    }
};
