<?php

namespace App\Services;

use App\Models\{RawMaterial, RawMaterialPurchase};
use Illuminate\Support\Facades\DB;

/**
 * Records a raw material purchase as a single, self-contained
 * transaction: one supplier invoice can cover multiple materials (a
 * real header + multiple lines, not one purchase per material). For
 * each line: increases that material's stock, updates its latest cost,
 * and posts one consolidated ledger entry — never creating a separate
 * Expense record, so the same purchase is never entered twice. Cash
 * payments correctly feed Cash Reconciliation and bank payments
 * correctly feed Bank Reconciliation simply by posting to the real
 * ledger accounts those features already read from — no separate
 * integration needed.
 */
class RawMaterialPurchaseService
{
    public function __construct(private NumberingService $numbers, private AccountingService $accounting) {}

    /**
     * @param array $data Header fields (supplier_id, purchase_date,
     * payment_method, bank_account_id, payment_reference, notes,
     * invoice_fields) plus 'lines' => [['raw_material_id','quantity','unit','unit_price','tax_amount'], ...]
     */
    public function create(array $data): RawMaterialPurchase
    {
        return DB::transaction(function () use ($data) {
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $lineData = [];

            foreach ($data['lines'] as $line) {
                $lineSubtotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $lineTax = round((float) ($line['tax_amount'] ?? 0), 2);
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
                $lineData[] = $line + ['line_subtotal' => $lineSubtotal, 'line_tax' => $lineTax, 'line_total' => round($lineSubtotal + $lineTax, 2)];
            }
            $subtotal = round($subtotal, 2);
            $taxTotal = round($taxTotal, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);

            $purchase = RawMaterialPurchase::create([
                'purchase_number' => $this->numbers->next('raw_material_purchase'),
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxTotal,
                'total_amount' => $grandTotal,
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $data['payment_method'] === 'bank' ? ($data['bank_account_id'] ?? null) : null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ] + ($data['invoice_fields'] ?? []));

            foreach ($lineData as $line) {
                $material = RawMaterial::findOrFail($line['raw_material_id']);

                $purchase->lines()->create([
                    'raw_material_id' => $material->id,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['line_tax'],
                    'line_total' => $line['line_total'],
                ]);

                // Stock increases immediately; latest_cost reflects this line's real unit price.
                $material->increment('current_stock', (float) $line['quantity']);
                $material->update(['latest_cost' => $line['unit_price']]);
            }

            $this->postToLedger($purchase, $subtotal, $taxTotal, $grandTotal, $data['payment_method']);

            if ($data['payment_method'] === 'unpaid') {
                $purchase->supplier()->increment('outstanding_payable', $grandTotal);
            }

            return $purchase->fresh('lines.rawMaterial');
        });
    }

    private function postToLedger(RawMaterialPurchase $purchase, float $subtotal, float $taxTotal, float $grandTotal, string $paymentMethod): void
    {
        $lines = [['account' => '1200', 'debit' => $subtotal, 'credit' => 0]];
        if ($taxTotal > 0) $lines[] = ['account' => '1300', 'debit' => $taxTotal, 'credit' => 0];

        $creditAccount = match ($paymentMethod) {
            'cash' => '1000',
            'bank' => $purchase->bankAccount?->code ?? '1010',
            'unpaid' => '2000', // Accounts Payable — Supplier Payable
            default => '2000',
        };
        $lines[] = ['account' => $creditAccount, 'debit' => 0, 'credit' => $grandTotal];

        $this->accounting->post($purchase, "Raw material purchase {$purchase->purchase_number}", $lines, $purchase->purchase_date->toDateString());
    }

    /**
     * @return array{previous: ?float, latest: float, lowest: float, highest: float, change_percent: ?float, by_supplier: array}
     */
    public function priceHistory(RawMaterial $material): array
    {
        $lines = $material->purchaseLines()->with('purchase.supplier')->get()
            ->sortBy(fn ($l) => [$l->purchase->purchase_date, $l->id])->values();

        if ($lines->isEmpty()) {
            return ['previous' => null, 'latest' => null, 'lowest' => null, 'highest' => null, 'change_percent' => null, 'by_supplier' => []];
        }

        $latest = (float) $lines->last()->unit_price;
        $previous = $lines->count() > 1 ? (float) $lines->slice(-2, 1)->first()->unit_price : null;
        $lowest = (float) $lines->min('unit_price');
        $highest = (float) $lines->max('unit_price');
        $changePercent = ($previous !== null && $previous > 0) ? round((($latest - $previous) / $previous) * 100, 1) : null;

        $bySupplier = $lines->groupBy(fn ($l) => $l->purchase->supplier_id)->map(function ($group) {
            $last = $group->sortBy(fn ($l) => $l->purchase->purchase_date)->last();
            return ['supplier' => $last->purchase->supplier, 'latest_price' => (float) $last->unit_price, 'purchase_count' => $group->count()];
        })->values()->all();

        return ['previous' => $previous, 'latest' => $latest, 'lowest' => $lowest, 'highest' => $highest, 'change_percent' => $changePercent, 'by_supplier' => $bySupplier];
    }
}
