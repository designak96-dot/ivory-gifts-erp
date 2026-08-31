<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('page'); // which listing page this applies to, e.g. 'invoices', 'orders', 'customers', 'inventory'
            $table->json('params'); // the actual query-string params to re-apply
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Pre-seed the exact example filters named in the request, so
        // the feature is immediately useful without requiring the owner
        // to build them from scratch first.
        $now = now();
        \DB::table('saved_filters')->insert([
            ['name' => 'Unpaid over AED 1,000', 'page' => 'invoices', 'params' => json_encode(['min_outstanding' => 1000]), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Due this week', 'page' => 'orders', 'params' => json_encode(['due' => 'this_week']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Need Design', 'page' => 'orders', 'params' => json_encode(['design_status' => 'need_design']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'VIP Customers', 'page' => 'customers', 'params' => json_encode(['tag' => 'VIP']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Low Stock', 'page' => 'inventory', 'params' => json_encode(['status' => 'low']), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
