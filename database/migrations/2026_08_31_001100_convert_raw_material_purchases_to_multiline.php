<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Converts raw material purchases from single-material-per-purchase to
 * a real header + multiple lines structure — one supplier invoice can
 * now cover several materials. Existing data (from the previous,
 * single-line version of this feature) is safely migrated, not
 * discarded: every existing header row becomes one header + one line,
 * preserving the exact same purchase. No doctrine/dbal is available in
 * this environment, so this uses add-column + copy-data + drop-column
 * throughout rather than renameColumn/change().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_material_purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('unit');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::table('raw_material_purchases', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->default(0)->after('supplier_id');
        });

        // Migrate every existing single-line purchase into the new
        // header+line structure before the old columns are removed.
        if (Schema::hasColumn('raw_material_purchases', 'raw_material_id')) {
            DB::table('raw_material_purchases')->orderBy('id')->chunk(200, function ($purchases) {
                foreach ($purchases as $p) {
                    if ($p->raw_material_id === null) continue; // already migrated / has no legacy single-line data
                    DB::table('raw_material_purchase_lines')->insert([
                        'raw_material_purchase_id' => $p->id,
                        'raw_material_id' => $p->raw_material_id,
                        'quantity' => $p->quantity,
                        'unit' => $p->unit,
                        'unit_price' => $p->unit_price,
                        'tax_amount' => $p->tax_amount,
                        'line_total' => $p->total_amount,
                        'created_at' => $p->created_at,
                        'updated_at' => $p->updated_at,
                    ]);
                    DB::table('raw_material_purchases')->where('id', $p->id)->update([
                        'subtotal' => (float) $p->total_amount - (float) $p->tax_amount,
                    ]);
                }
            });
        }

        Schema::table('raw_material_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('raw_material_purchases', 'raw_material_id')) {
                $table->dropForeign(['raw_material_id']);
                $table->dropColumn(['raw_material_id', 'quantity', 'unit', 'unit_price']);
            }
        });

        // tax_amount and total_amount are kept on the header — they now
        // represent the aggregate across all lines (renamed in meaning,
        // not in column name, so no renameColumn/dbal dependency needed).
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
