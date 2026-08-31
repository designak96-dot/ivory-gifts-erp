<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('blue');
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'tag_id']);
        });

        // Seed the example tags named explicitly in the request, so the
        // feature is immediately usable rather than requiring the owner
        // to type them in from scratch.
        $now = now();
        \DB::table('tags')->insert(collect([
            ['name' => 'VIP', 'color' => 'amber'],
            ['name' => 'Corporate', 'color' => 'blue'],
            ['name' => 'Repeat Customer', 'color' => 'green'],
            ['name' => 'High Value', 'color' => 'blue'],
            ['name' => 'Payment Risk', 'color' => 'red'],
            ['name' => 'Wholesale', 'color' => 'green'],
        ])->map(fn ($t) => $t + ['created_at' => $now, 'updated_at' => $now])->all());
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
