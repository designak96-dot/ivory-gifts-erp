<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('package_size', 20)->default('standard')->after('status')->index();
            $table->decimal('delivery_charge', 10, 2)->default(0)->after('package_size');
            $table->string('location_url')->nullable()->after('delivery_charge');
            $table->string('recipient_name')->nullable()->after('location_url');
            $table->unsignedSmallInteger('attempt_count')->default(0)->after('recipient_name');
            $table->text('failure_reason')->nullable()->after('attempt_count');
            $table->foreignId('last_updated_by')->nullable()->after('failure_reason')->constrained('users')->nullOnDelete();
            $table->index(['delivery_date', 'status'], 'delivery_date_status_index');
            $table->index(['driver_id', 'delivery_date'], 'delivery_driver_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropIndex('delivery_date_status_index');
            $table->dropIndex('delivery_driver_date_index');
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropColumn(['package_size', 'delivery_charge', 'location_url', 'recipient_name', 'attempt_count', 'failure_reason']);
        });
    }
};
