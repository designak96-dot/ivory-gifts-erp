<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\DataResetService;
use Illuminate\Console\Command;

class ResetToProductsOnly extends Command
{
    protected $signature = 'ivory:reset-to-products-only {--force : Skip the confirmation prompt (use with care)}';
    protected $description = 'Delete all trial/transactional data, keeping only Sales Products and structural configuration';

    public function handle(DataResetService $resetService): int
    {
        if (!$this->option('force')) {
            $this->warn('This permanently deletes ALL customers, orders, invoices, payments, expenses, the general ledger, audit log, and every other trial record.');
            $this->warn('Sales Products, categories, tax rates, chart of accounts structure, settings, and user accounts are KEPT.');
            $confirmed = $this->ask('Type CONFIRM to proceed') === 'CONFIRM';
            if (!$confirmed) {
                $this->info('Cancelled — nothing was changed.');
                return self::FAILURE;
            }
        }

        $productCountBefore = Product::count();
        $result = $resetService->resetToProductsOnly(null);

        $this->info("Done. Products preserved: {$result['products_preserved']} (was {$productCountBefore} before — must match).");
        if ($result['products_preserved'] !== $productCountBefore) {
            $this->error('Product count changed unexpectedly — investigate before trusting this reset.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
