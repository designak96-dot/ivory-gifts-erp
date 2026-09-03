<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Adds the structural pieces needed for the Finance & Order Migration
 * Import system: duplicate-import protection (file hash), a new Income
 * Records table (Other Income / Delivery Income have no existing home
 * — Invoice is tied to Sales Orders specifically), migration source
 * tracking + missing-proof flagging on expenses and raw material
 * purchases, and a Migration Clearing account for payments whose
 * method genuinely can't be determined from old records. Purely
 * additive — extends the existing data_imports table rather than
 * building a parallel audit mechanism. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_imports', function (Blueprint $table) {
            $table->string('file_hash')->nullable()->index()->after('type');
            $table->string('original_filename')->nullable()->after('file_hash');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('proof_missing')->default(false)->after('proof_size');
            $table->string('source_sheet')->nullable()->after('proof_missing');
            $table->string('source_row')->nullable()->after('source_sheet');
            $table->unsignedBigInteger('import_batch_id')->nullable()->index()->after('source_row');
        });

        Schema::table('raw_material_purchases', function (Blueprint $table) {
            $table->boolean('proof_missing')->default(false)->after('invoice_size');
            $table->string('source_sheet')->nullable()->after('proof_missing');
            $table->string('source_row')->nullable()->after('source_sheet');
            $table->unsignedBigInteger('import_batch_id')->nullable()->index()->after('source_row');
        });

        Schema::create('income_records', function (Blueprint $table) {
            $table->id();
            $table->string('income_number')->unique();
            $table->date('income_date')->index();
            $table->string('category')->index(); // other | ivory_delivery | ifast_delivery
            $table->string('customer_details')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount_ex_tax', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('payment_method')->nullable(); // cash | bank | card | migration_clearing
            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('proof_missing')->default(false);
            $table->string('source_sheet')->nullable();
            $table->string('source_row')->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Migration Clearing — a temporary holding account for payments
        // whose real method (cash/bank/card) can't be determined from
        // old records. Must be reconciled to a real account later by
        // reviewing and reclassifying each cleared transaction.
        if (!DB::table('chart_of_accounts')->where('code', '1050')->exists()) {
            DB::table('chart_of_accounts')->insert([
                'code' => '1050', 'name' => 'Migration Clearing', 'type' => 'asset',
                'account_subtype' => 'clearing', 'is_active' => true, 'opening_balance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
