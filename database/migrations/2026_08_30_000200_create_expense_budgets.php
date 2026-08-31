<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('month'); // Y-m-01
            $table->decimal('budget_amount', 14, 2);
            $table->timestamps();
            $table->unique(['category', 'month']);
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
