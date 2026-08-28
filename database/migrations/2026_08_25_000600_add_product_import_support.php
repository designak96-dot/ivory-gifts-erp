<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source_id')->nullable()->unique()->after('sku');
            $table->string('image_path')->nullable()->after('description');
            $table->string('thumbnail_path')->nullable()->after('image_path');
            $table->unsignedInteger('min_qty')->default(1)->after('unit');
            $table->unsignedInteger('sort_order')->default(0)->after('min_qty');
        });

        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending')->index(); // pending, previewed, completed, failed
            $table->boolean('is_dry_run')->default(true);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('missing_image_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $table->string('source_id')->nullable();
            $table->string('name')->nullable();
            $table->string('outcome'); // created, updated, skipped, error
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['product_import_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['source_id', 'image_path', 'thumbnail_path', 'min_qty', 'sort_order']);
        });
    }
};
