<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('open'); // open, in_progress, done, cancelled
            $table->boolean('reminder_enabled')->default(false);
            // Polymorphic link — Sales Order, Customer, Invoice, Product, or Supplier.
            $table->nullableMorphs('linkable');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
