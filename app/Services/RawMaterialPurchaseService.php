<?php

namespace App\Services;

use App\Models\{RawMaterial, RawMaterialPurchase};
use Illuminate\Support\Facades\DB;

/**
 * Records a raw material purchase as a single, self-contained
 * transaction: increases stock, updates the material's latest cost,
 * and posts directly to the general ledger — never creating a separate
 * Expense record, so the same purchase is never entered twice. Cash
 * payments correctly feed Cash Reconciliation and bank payments
 * correctly feed Bank Reconciliation simply by posting to the real
 * ledger accounts those features already read from — no separate
 * integration needed.
 */
class RawMaterialPurchaseService
{
    public function __construct(private NumberingService $numbers, private AccountingService $accounting) {}

    public function create(array $data): RawMaterialPurchase
    {
        return DB::transaction(function () use ($data) {
            $material = RawMaterial::findOrFail($data['raw_material_id']);

            $amountExTax = round((float) $data['quantity'] * (float) $data['unit_price'], 2);
            $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
            $totalAmount = round($amountExTax + $taxAmount, 2);

            $purchase = RawMaterialPurchase::create([
                'purchase_number' => $this->numbers->next('raw_material_purchase'),
                'supplier_id' => $data['supplier_id'],
                'raw_material_id' => $material->id,
                'purchase_date' => $data['purchase_date'],
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'unit_price' => $data['unit_price'],
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $data['payment_method'] === 'bank' ? ($data['bank_account_id'] ?? null) : null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ] + ($data['invoice_fields'] ?? []));

            // Stock increases immediately; latest_cost reflects this purchase's real unit price.
            $material->increment('current_stock', (float) $data['quantity']);
            $material->update(['latest_cost' => $data['unit_price']]);

            $this->postToLedger($purchase, $amountExTax, $taxAmount, $totalAmount, $data['payment_method']);

            if ($data['payment_method'] === 'unpaid') {
                $purchase->supplier()->increment('outstanding_payable', $totalAmount);
            }

            return $purchase->fresh();
        });
    }

    private function postToLedger(RawMaterialPurchase $purchase, float $amountExTax, float $taxAmount, float $totalAmount, string $paymentMethod): void
    {
        $lines = [['account' => '1200', 'debit' => $amountExTax, 'credit' => 0]];
        if ($taxAmount > 0) $lines[] = ['account' => '1300', 'debit' => $taxAmount, 'credit' => 0];

        $creditAccount = match ($paymentMethod) {
            'cash' => '1000',
            'bank' => $purchase->bankAccount?->code ?? '1010',
            'unpaid' => '2000', // Accounts Payable — Supplier Payable
            default => '2000',
        };
        $lines[] = ['account' => $creditAccount, 'debit' => 0, 'credit' => $totalAmount];

        $this->accounting->post($purchase, "Raw material purchase {$purchase->purchase_number}", $lines, $purchase->purchase_date->toDateString());
    }

    /**
     * @return array{previous: ?float, latest: float, lowest: float, highest: float, change_percent: ?float, by_supplier: array}
     */
    public function priceHistory(RawMaterial $material): array
    {
        $purchases = $material->purchases()->orderBy('purchase_date')->orderBy('id')->get();
        if ($purchases->isEmpty()) {
            return ['previous' => null, 'latest' => null, 'lowest' => null, 'highest' => null, 'change_percent' => null, 'by_supplier' => []];
        }

        $latest = (float) $purchases->last()->unit_price;
        $previous = $purchases->count() > 1 ? (float) $purchases->slice(-2, 1)->first()->unit_price : null;
        $lowest = (float) $purchases->min('unit_price');
        $highest = (float) $purchases->max('unit_price');
        $changePercent = ($previous !== null && $previous > 0) ? round((($latest - $previous) / $previous) * 100, 1) : null;

        $bySupplier = $purchases->groupBy('supplier_id')->map(function ($group) {
            $last = $group->sortBy('purchase_date')->last();
            return ['supplier' => $last->supplier, 'latest_price' => (float) $last->unit_price, 'purchase_count' => $group->count()];
        })->values()->all();

        return ['previous' => $previous, 'latest' => $latest, 'lowest' => $lowest, 'highest' => $highest, 'change_percent' => $changePercent, 'by_supplier' => $bySupplier];
    }
}
