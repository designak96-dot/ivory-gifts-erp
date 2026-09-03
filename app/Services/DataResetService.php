<?php

namespace App\Services;

use App\Models\{ChartOfAccount, NumberingSequence, Product};
use Illuminate\Support\Facades\{DB, Log, Schema};

/**
 * Wipes every trial/transactional record while keeping the Sales
 * Products catalog and the structural configuration it depends on
 * (categories, tax rates, chart of accounts, settings, user accounts).
 * Shared by both the CLI command and the Settings-page action, so
 * there is exactly one tested implementation of this destructive
 * operation, not two that could drift apart.
 */
class DataResetService
{
    private const WIPE_TABLES = [
        'quotation_items', 'quotation_versions', 'quotations',
        'sales_order_items', 'sales_order_status_history', 'sales_order_monthly_counters', 'sales_orders',
        'order_attachments', 'order_comments', 'customer_share_links', 'customer_tag',
        'delivery_notes', 'production_job_costs', 'production_jobs',
        'invoice_items', 'invoices', 'payment_allocations', 'payments', 'credit_note_items', 'credit_notes',
        'expenses', 'expense_budgets', 'raw_material_purchase_lines', 'raw_material_purchases', 'raw_materials', 'suppliers',
        'journal_lines', 'journal_entries', 'account_transfers',
        'cash_adjustments', 'cash_reconciliations', 'bank_statement_transactions', 'bank_reconciliations',
        'customers',
        'stock_items', 'stock_movements',
        'data_import_rows', 'data_imports', 'product_import_rows', 'product_imports',
        'tasks', 'saved_filters', 'sync_logs', 'audit_logs', 'backups',
    ];

    /** @return array{products_preserved: int} */
    public function resetToProductsOnly(?int $performedByUserId): array
    {
        $productCountBefore = Product::count();
        $driver = DB::connection()->getDriverName();

        DB::transaction(function () use ($driver) {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            if (Schema::hasColumn('products', 'supplier_id')) {
                DB::table('products')->update(['supplier_id' => null]);
            }

            foreach (self::WIPE_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            ChartOfAccount::query()->update(['opening_balance' => 0]);
            NumberingSequence::query()->update(['current_value' => 0]);
        });

        Log::warning('Data reset to products-only executed', [
            'by_user_id' => $performedByUserId,
            'products_preserved' => $productCountBefore,
            'at' => now()->toDateTimeString(),
        ]);

        return ['products_preserved' => Product::count()];
    }
}
