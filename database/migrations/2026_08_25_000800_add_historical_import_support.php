<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('source_id')->nullable()->unique()->after('customer_code');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('source_order_number')->nullable()->unique()->after('order_number');
        });

        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // customers, orders
            $table->string('status')->default('pending')->index();
            $table->boolean('is_dry_run')->default(true);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained()->cascadeOnDelete();
            $table->string('source_id')->nullable();
            $table->string('label')->nullable();
            $table->string('outcome'); // created, updated, skipped, conflict, error
            $table->text('message')->nullable();
            $table->json('existing_values')->nullable();
            $table->json('incoming_values')->nullable();
            $table->timestamps();

            $table->index(['data_import_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_rows');
        Schema::dropIfExists('data_imports');
        Schema::table('sales_orders', fn (Blueprint $t) => $t->dropColumn('source_order_number'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('source_id'));
    }
};
