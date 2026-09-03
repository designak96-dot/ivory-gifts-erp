<?php

namespace App\Services;

use App\Models\{DataImport, DataImportRow, Expense, IncomeRecord, RawMaterial, RawMaterialPurchase, RawMaterialPurchaseLine, Supplier};
use Illuminate\Support\Facades\DB;

/**
 * Migrates old finance workbook rows into real Purchase, Expense, and
 * Income records — never writing directly to Expenses for genuine
 * material/COGS purchases (those become RawMaterialPurchase, matching
 * how normal purchase entry already works), and never auto-creating a
 * Supplier record for a staff salary payee.
 *
 * VAT handling never double-counts: `amount` is treated as the pre-VAT
 * figure and `total_amount_tax` as the VAT-inclusive figure; VAT is
 * always their difference, never separately recomputed. If the two are
 * equal, VAT is zero. If the implied rate is close to 5%, that's
 * accepted as normal; otherwise a warning is raised for the row rather
 * than silently trusting either number.
 */
class FinanceMigrationImportService
{
    private const VAT_TOLERANCE_PERCENT = 1.0;

    private const MATERIAL_PURCHASE_CATEGORY_ALIASES = [
        'material purchases - ivory gifts (cogs)', 'material purchases - ivory garments (cogs)',
        'material purchases (cogs)', 'material purchase', 'material purchases', 'cogs', 'raw material purchase',
    ];

    private const RENT_CATEGORY_ALIASES = ['rent expense', 'rent', 'lease', 'rental'];
    private const SALARY_CATEGORY_ALIASES = ['salary', 'salaries', 'staff salary', 'payroll'];

    public function __construct(private NumberingService $numbers) {}

    // ---------------------------------------------------------------
    // Shared VAT reconciliation — never double-counts VAT.
    // ---------------------------------------------------------------

    /** @return array{amount_ex_tax: float, tax_amount: float, total_amount: float, warning: ?string} */
    public function reconcileAmounts(float $amount, ?float $totalWithTax): array
    {
        if ($totalWithTax === null) {
            return ['amount_ex_tax' => round($amount, 2), 'tax_amount' => 0.0, 'total_amount' => round($amount, 2), 'warning' => null];
        }

        $vat = round($totalWithTax - $amount, 2);
        $warning = null;

        if (abs($vat) < 0.01) {
            $vat = 0.0; // equal amounts -> genuinely zero VAT, not a rounding artifact to flag
        } else {
            $impliedRate = $amount != 0 ? ($vat / $amount) * 100 : 999;
            $closeToFive = abs($impliedRate - 5) <= self::VAT_TOLERANCE_PERCENT;
            if (!$closeToFive && $vat > 0) {
                $warning = "Implied VAT of AED {$vat} on AED {$amount} is ~".round($impliedRate, 1)."%, not close to the standard 5% — please confirm.";
            } elseif ($vat < 0) {
                $warning = "Total Amount + Tax (AED {$totalWithTax}) is LESS than Amount (AED {$amount}) — please confirm which figure is correct.";
            }
        }

        return ['amount_ex_tax' => round($amount, 2), 'tax_amount' => round($vat, 2), 'total_amount' => round($totalWithTax, 2), 'warning' => $warning];
    }

    public static function normalizeSupplierName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    private function findOrCreateSupplier(string $rawName): Supplier
    {
        $normalized = self::normalizeSupplierName($rawName);
        // Case-insensitive, whitespace-collapsed match against every existing supplier —
        // never create "Blue Rhine" and "blue  rhine" as two different suppliers.
        $existing = Supplier::all()->first(fn ($s) => self::normalizeSupplierName($s->name) === $normalized);
        if ($existing) {
            return $existing;
        }
        return Supplier::create(['supplier_code' => 'SUP-'.str_pad((string) (Supplier::max('id') + 1), 5, '0', STR_PAD_LEFT), 'name' => trim($rawName), 'status' => 'active']);
    }

    private function findOrCreateMaterial(string $rawName): RawMaterial
    {
        $normalized = strtolower(trim($rawName));
        $existing = RawMaterial::all()->first(fn ($m) => strtolower(trim($m->name)) === $normalized);
        if ($existing) {
            return $existing;
        }
        return RawMaterial::create(['code' => 'MAT-'.str_pad((string) (RawMaterial::max('id') + 1), 5, '0', STR_PAD_LEFT), 'name' => trim($rawName), 'unit' => 'unit']);
    }

    /** Resolves a payment method against the given migration mapping, or 'migration_clearing' if genuinely unresolved — never silently defaults to Cash. */
    private function resolvePaymentMethod(?string $sourceValue, array $paymentMethodMap): string
    {
        $key = strtolower(trim((string) $sourceValue));
        if ($key === '') {
            return $paymentMethodMap['__unmapped__'] ?? 'migration_clearing';
        }
        return $paymentMethodMap[$key] ?? ($paymentMethodMap['__unmapped__'] ?? 'migration_clearing');
    }

    private function categoryMatches(string $category, array $aliases): bool
    {
        $normalized = strtolower(trim($category));
        foreach ($aliases as $alias) {
            if (str_contains($normalized, $alias)) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------
    // Preview — classifies every row without writing anything.
    // ---------------------------------------------------------------

    public function preview(array $rows): array
    {
        $materialGroups = [];
        $expenseRows = [];
        $newSuppliers = [];
        $existingSuppliers = [];
        $totalExTax = 0.0;
        $totalTax = 0.0;
        $warnings = [];
        $fatalErrors = [];

        foreach ($rows as $i => $row) {
            $category = trim((string) ($row['expense_category'] ?? $row['category'] ?? ''));
            $amount = $row['amount'] ?? null;
            $totalTax = isset($row['total_amount + tax']) ? (float) $row['total_amount + tax'] : (isset($row['total_amount_tax']) ? (float) $row['total_amount_tax'] : null);

            if ($amount === null || $amount === '') {
                $fatalErrors[] = "Row ".($i + 1).": missing Amount.";
                continue;
            }

            $reconciled = $this->reconcileAmounts((float) $amount, $totalTax !== null ? (float) $totalTax : null);
            if ($reconciled['warning']) {
                $warnings[] = "Row ".($i + 1).": {$reconciled['warning']}";
            }
            $totalExTax += $reconciled['amount_ex_tax'];

            if ($this->categoryMatches($category, self::MATERIAL_PURCHASE_CATEGORY_ALIASES)) {
                $supplierRaw = trim((string) ($row['supplier'] ?? 'Unknown Supplier'));
                $invoiceNo = trim((string) ($row['invoice no'] ?? $row['invoice_no'] ?? ''));
                $date = trim((string) ($row['date'] ?? ''));
                $groupKey = self::normalizeSupplierName($supplierRaw).'|'.$invoiceNo.'|'.$date;
                $materialGroups[$groupKey][] = $row;

                $normalized = self::normalizeSupplierName($supplierRaw);
                $existsAlready = Supplier::all()->contains(fn ($s) => self::normalizeSupplierName($s->name) === $normalized);
                if ($existsAlready) {
                    $existingSuppliers[$normalized] = $supplierRaw;
                } else {
                    $newSuppliers[$normalized] = $supplierRaw;
                }
            } else {
                $expenseRows[] = $row;
            }
        }

        return [
            'total_rows' => count($rows),
            'material_purchase_groups' => count($materialGroups),
            'expense_income_rows' => count($expenseRows),
            'new_suppliers' => array_values($newSuppliers),
            'existing_matched_suppliers' => array_values($existingSuppliers),
            'total_ex_tax' => round($totalExTax, 2),
            'warnings' => $warnings,
            'fatal_errors' => $fatalErrors,
            'can_commit' => count($fatalErrors) === 0,
        ];
    }

    // ---------------------------------------------------------------
    // Commit — Material Purchases
    // ---------------------------------------------------------------

    public function commitMaterialPurchases(array $rows, int $userId, bool $isDryRun, array $paymentMethodMap = []): DataImport
    {
        $import = DataImport::create(['type' => 'material_purchases', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $skipped = $errors = 0;

        $groups = [];
        foreach ($rows as $row) {
            $category = trim((string) ($row['expense_category'] ?? $row['category'] ?? ''));
            if (!$this->categoryMatches($category, self::MATERIAL_PURCHASE_CATEGORY_ALIASES)) {
                continue;
            }
            $supplierRaw = trim((string) ($row['supplier'] ?? ''));
            $invoiceNo = trim((string) ($row['invoice no'] ?? $row['invoice_no'] ?? ''));
            $date = trim((string) ($row['date'] ?? ''));
            if ($supplierRaw === '' || $invoiceNo === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $supplierRaw ?: 'Unknown', 'outcome' => 'skipped', 'message' => 'Missing supplier or invoice number for a material purchase row.']);
                continue;
            }
            $groupKey = self::normalizeSupplierName($supplierRaw).'|'.$invoiceNo.'|'.$date;
            $groups[$groupKey][] = $row;
        }

        foreach ($groups as $lines) {
            $first = $lines[0];
            $invoiceNo = trim((string) ($first['invoice no'] ?? $first['invoice_no'] ?? ''));
            $supplierRaw = trim((string) $first['supplier']);

            try {
                // Idempotency: same supplier + invoice number + date must not create a second purchase document.
                $normalizedSupplier = self::normalizeSupplierName($supplierRaw);
                $existingSupplier = Supplier::all()->first(fn ($s) => self::normalizeSupplierName($s->name) === $normalizedSupplier);
                $existingPurchase = $existingSupplier ? RawMaterialPurchase::where('supplier_id', $existingSupplier->id)->where('payment_reference', $invoiceNo)->first() : null;

                if ($existingPurchase) {
                    $skipped++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $invoiceNo, 'label' => $supplierRaw, 'outcome' => 'skipped', 'message' => "Invoice {$invoiceNo} for this supplier was already imported (purchase #{$existingPurchase->id})."]);
                    continue;
                }

                if ($isDryRun) {
                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $invoiceNo, 'label' => $supplierRaw, 'outcome' => 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($lines, $first, $invoiceNo, $supplierRaw, $paymentMethodMap, $import, &$created) {
                    $supplier = $this->findOrCreateSupplier($supplierRaw);
                    $date = !empty($first['date']) ? \Carbon\Carbon::parse($first['date']) : now();
                    $paymentMethod = $this->resolvePaymentMethod($first['payment method'] ?? $first['payment_method'] ?? null, $paymentMethodMap);
                    $proofMissing = empty($first['proof']) && empty($first['proof_path']);

                    $subtotal = 0.0;
                    $taxTotal = 0.0;
                    $lineData = [];
                    foreach ($lines as $line) {
                        $amount = (float) ($line['amount'] ?? 0);
                        $totalTax = isset($line['total_amount + tax']) ? (float) $line['total_amount + tax'] : (isset($line['total_amount_tax']) ? (float) $line['total_amount_tax'] : null);
                        $reconciled = $this->reconcileAmounts($amount, $totalTax);
                        $subtotal += $reconciled['amount_ex_tax'];
                        $taxTotal += $reconciled['tax_amount'];
                        $materialName = trim((string) ($line['description'] ?? $line['category'] ?? 'Imported material'));
                        // Quantity is a clearly marked migration fallback when the source doesn't provide it —
                        // never silently presented as a real recorded quantity.
                        $hasRealQty = !empty($line['quantity']);
                        $qty = $hasRealQty ? (float) $line['quantity'] : 1.0;
                        $lineData[] = ['material' => $materialName, 'qty' => $qty, 'unit_price' => $qty > 0 ? round($reconciled['amount_ex_tax'] / $qty, 4) : $reconciled['amount_ex_tax'], 'tax' => $reconciled['tax_amount'], 'total' => round($reconciled['amount_ex_tax'] + $reconciled['tax_amount'], 2), 'qty_is_fallback' => !$hasRealQty];
                    }
                    $grandTotal = round($subtotal + $taxTotal, 2);

                    $purchase = RawMaterialPurchase::create([
                        'purchase_number' => $this->numbers->next('raw_material_purchase'),
                        'supplier_id' => $supplier->id, 'purchase_date' => $date,
                        'subtotal' => round($subtotal, 2), 'tax_amount' => round($taxTotal, 2), 'total_amount' => $grandTotal,
                        'payment_method' => in_array($paymentMethod, ['cash', 'bank'], true) ? $paymentMethod : 'unpaid',
                        'payment_reference' => $invoiceNo,
                        'notes' => $paymentMethod === 'migration_clearing' ? 'Payment method unresolved at migration — routed to Migration Clearing, requires reconciliation.' : null,
                        'proof_missing' => $proofMissing, 'source_sheet' => 'finance_migration', 'source_row' => $invoiceNo,
                        'import_batch_id' => $import->id, 'created_by' => auth()->id(),
                    ]);

                    foreach ($lineData as $ld) {
                        $material = $this->findOrCreateMaterial($ld['material']);
                        RawMaterialPurchaseLine::create(['raw_material_purchase_id' => $purchase->id, 'raw_material_id' => $material->id, 'quantity' => $ld['qty'], 'unit' => 'unit', 'unit_price' => $ld['unit_price'], 'tax_amount' => $ld['tax'], 'line_total' => $ld['total']]);
                    }

                    if (in_array($supplier->outstanding_payable ?? null, [null], true) === false && $paymentMethod === 'unpaid') {
                        $supplier->increment('outstanding_payable', $grandTotal);
                    }

                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $invoiceNo, 'label' => $supplierRaw, 'outcome' => 'created', 'message' => count($lineData) > 1 ? count($lineData).' item lines grouped into one purchase.' : null]);
                });
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $invoiceNo, 'label' => $supplierRaw, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'skipped_count' => $skipped, 'error_count' => $errors]);
        return $import;
    }

    // ---------------------------------------------------------------
    // Commit — General Expenses / Rent / Salaries
    // ---------------------------------------------------------------

    public function commitExpenses(array $rows, int $userId, bool $isDryRun, array $paymentMethodMap = []): DataImport
    {
        $import = DataImport::create(['type' => 'general_expenses', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $skipped = $errors = 0;

        foreach ($rows as $i => $row) {
            $category = trim((string) ($row['expense_category'] ?? $row['category'] ?? ''));
            if ($this->categoryMatches($category, self::MATERIAL_PURCHASE_CATEGORY_ALIASES)) {
                continue; // handled by commitMaterialPurchases
            }
            $amount = $row['amount'] ?? null;
            if ($amount === null || $amount === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $category ?: null, 'outcome' => 'skipped', 'message' => "Row ".($i + 1).": missing Amount."]);
                continue;
            }

            try {
                if ($isDryRun) {
                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'label' => $category, 'outcome' => 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($row, $category, $paymentMethodMap, $import, &$created) {
                    $totalTax = isset($row['total_amount + tax']) ? (float) $row['total_amount + tax'] : (isset($row['total_amount_tax']) ? (float) $row['total_amount_tax'] : null);
                    $isSalary = $this->categoryMatches($category, self::SALARY_CATEGORY_ALIASES);
                    $isRent = $this->categoryMatches($category, self::RENT_CATEGORY_ALIASES);
                    // Salaries never carry VAT, regardless of the source figures.
                    $reconciled = $isSalary
                        ? ['amount_ex_tax' => round((float) $row['amount'], 2), 'tax_amount' => 0.0, 'total_amount' => round((float) $row['amount'], 2)]
                        : $this->reconcileAmounts((float) $row['amount'], $totalTax);

                    $date = !empty($row['date']) ? \Carbon\Carbon::parse($row['date']) : now();
                    $payee = trim((string) ($row['payee'] ?? $row['supplier'] ?? ''));
                    $description = trim((string) ($row['description'] ?? $category));
                    $paymentMethod = $this->resolvePaymentMethod($row['payment method'] ?? $row['payment_method'] ?? null, $paymentMethodMap);
                    $proofMissing = empty($row['proof']) && empty($row['proof_path']);

                    Expense::create([
                        'expense_number' => $this->numbers->next('expense'),
                        'expense_date' => $date,
                        'category' => $isRent ? 'Rent Expense' : ($isSalary ? 'Salaries' : $description),
                        'payee' => $payee ?: null,
                        'payment_method' => in_array($paymentMethod, ['cash', 'bank', 'card'], true) ? $paymentMethod : 'bank', // migration_clearing routed through bank-classified entry with a note, per Migration Clearing pattern
                        'amount_ex_tax' => $reconciled['amount_ex_tax'], 'tax_amount' => $reconciled['tax_amount'], 'total_amount' => $reconciled['total_amount'],
                        'reference' => $row['invoice no'] ?? $row['invoice_no'] ?? null,
                        'description' => $description,
                        'proof_missing' => $proofMissing, 'source_sheet' => 'finance_migration',
                        'source_row' => (string) ($row['invoice no'] ?? $row['invoice_no'] ?? $description),
                        'import_batch_id' => $import->id, 'created_by' => auth()->id(),
                    ]);

                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'label' => $description, 'outcome' => 'created']);
                });
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $category, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'skipped_count' => $skipped, 'error_count' => $errors]);
        return $import;
    }

    // ---------------------------------------------------------------
    // Commit — Other Income / Delivery Income
    // ---------------------------------------------------------------

    public function commitIncome(array $rows, int $userId, bool $isDryRun, string $incomeCategory, array $paymentMethodMap = []): DataImport
    {
        $import = DataImport::create(['type' => 'income_'.$incomeCategory, 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $skipped = $errors = 0;

        foreach ($rows as $i => $row) {
            $amount = $row['amount'] ?? null;
            if ($amount === null || $amount === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'outcome' => 'skipped', 'message' => "Row ".($i + 1).": missing Amount."]);
                continue;
            }

            try {
                if ($isDryRun) {
                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'outcome' => 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($row, $incomeCategory, $paymentMethodMap, $import, &$created) {
                    $totalTax = isset($row['total_amount + tax']) ? (float) $row['total_amount + tax'] : (isset($row['total_amount_tax']) ? (float) $row['total_amount_tax'] : null);
                    $reconciled = $this->reconcileAmounts((float) $row['amount'], $totalTax);
                    $date = !empty($row['date']) ? \Carbon\Carbon::parse($row['date']) : now();
                    $paymentMethod = $this->resolvePaymentMethod($row['payment method'] ?? $row['payment_method'] ?? null, $paymentMethodMap);
                    $proofMissing = empty($row['proof']) && empty($row['proof_path']);

                    IncomeRecord::create([
                        'income_number' => $this->numbers->next('income_record'),
                        'income_date' => $date, 'category' => $incomeCategory,
                        'customer_details' => $row['customer'] ?? $row['details'] ?? null,
                        'description' => $row['description'] ?? null,
                        'amount_ex_tax' => $reconciled['amount_ex_tax'], 'tax_amount' => $reconciled['tax_amount'], 'total_amount' => $reconciled['total_amount'],
                        'payment_method' => $paymentMethod, 'reference' => $row['invoice no'] ?? $row['invoice_no'] ?? null,
                        'remarks' => $row['remarks'] ?? null, 'proof_missing' => $proofMissing,
                        'source_sheet' => 'finance_migration', 'source_row' => $row['invoice no'] ?? null,
                        'import_batch_id' => $import->id, 'created_by' => auth()->id(),
                    ]);

                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'label' => $row['description'] ?? $incomeCategory, 'outcome' => 'created']);
                });
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'skipped_count' => $skipped, 'error_count' => $errors]);
        return $import;
    }
}
