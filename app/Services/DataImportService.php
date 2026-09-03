<?php

namespace App\Services;

use App\Models\{Customer, DataImport, DataImportRow, Invoice, InvoiceItem, ProductionJob, SalesOrder, SalesOrderItem};
use Illuminate\Support\Facades\DB;

/**
 * Historical customer and order import from CSV or JSON. XLSX is NOT
 * supported — no spreadsheet-parsing package is available in vendor/ and
 * none can be added without Packagist access in this environment. Export
 * to CSV first if your source is an .xlsx file; this is stated plainly in
 * the wizard UI rather than silently failing on upload.
 *
 * Customer matching priority (per the spec): normalized phone, then email,
 * then explicit source_id. Order matching: source_order_number only.
 *
 * Historical orders support multiple item lines per order: rows sharing
 * the same source_order_number are grouped into one order with multiple
 * SalesOrderItem/InvoiceItem lines — never flattened into one description.
 * A real Invoice is always created (not just a SalesOrder total), because
 * paid/remaining amounts are computed from linked invoices elsewhere in
 * the app — without one, a historical "paid" order would incorrectly
 * display as fully unpaid everywhere. Historical orders never trigger a
 * new pending delivery: they're read-only history, not active jobs.
 */
class DataImportService
{
    private const VAT_TOLERANCE = 0.05;

    public function __construct(private PhoneNormalizer $phones) {}

    public function parseFile(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'json') {
            $data = json_decode(file_get_contents($path), true);
            if (!is_array($data)) {
                throw new \RuntimeException('File did not decode to a JSON array.');
            }
            return $data;
        }
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($path);
        }
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            throw new \RuntimeException('XLSX is not supported in this environment (no spreadsheet library is available to install). Please export to CSV and re-upload.');
        }
        throw new \RuntimeException("Unsupported file extension: {$extension}");
    }

    public function fileHash(string $path): string
    {
        return hash_file('sha256', $path);
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));
        if (isset($headers[0]) && str_starts_with($headers[0], "\xEF\xBB\xBF")) {
            $headers[0] = substr($headers[0], 3); // strip a leading UTF-8 BOM if present
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad($row, count($headers), null));
        }
        fclose($handle);
        return $rows;
    }

    // ---------------------------------------------------------------
    // Customers
    // ---------------------------------------------------------------

    public function previewCustomers(array $rows): array
    {
        $preview = [];
        foreach ($rows as $row) {
            [$match, $reason] = $this->findCustomerMatch($row);
            $isConflict = $match && !$match->source_id;
            $preview[] = [
                'source_id' => $row['source_id'] ?? null,
                'name' => $row['name'] ?? '',
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'action' => $isConflict ? 'conflict' : ($match ? 'update' : 'create'),
                'matched_by' => $reason,
            ];
        }
        return ['rows' => $preview, 'total' => count($preview)];
    }

    public function commitCustomers(array $rows, int $userId, bool $isDryRun): DataImport
    {
        $import = DataImport::create(['type' => 'customers', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $updated = $skipped = $conflicts = $errors = 0;

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::transaction(function () use ($chunk, $import, $isDryRun, &$created, &$updated, &$skipped, &$conflicts, &$errors) {
                foreach ($chunk as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name === '') {
                        $skipped++;
                        DataImportRow::create(['data_import_id' => $import->id, 'label' => null, 'outcome' => 'skipped', 'message' => 'Missing required name.']);
                        continue;
                    }

                    try {
                        [$match, $reason] = $this->findCustomerMatch($row);

                        if ($match && !$match->source_id) {
                            $conflicts++;
                            DataImportRow::create([
                                'data_import_id' => $import->id, 'source_id' => $row['source_id'] ?? null, 'label' => $name,
                                'outcome' => 'conflict', 'message' => "Matched an existing customer (#{$match->id}) created directly in the ERP, matched by {$reason}. Not overwritten.",
                                'existing_values' => $match->only(['name', 'phone', 'email', 'emirate', 'area']),
                                'incoming_values' => ['name' => $name, 'phone' => $row['phone'] ?? null, 'email' => $row['email'] ?? null, 'emirate' => $row['emirate'] ?? null, 'area' => $row['area'] ?? null],
                            ]);
                            continue;
                        }

                        if ($isDryRun) {
                            $match ? $updated++ : $created++;
                            DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $row['source_id'] ?? null, 'label' => $name, 'outcome' => $match ? 'updated' : 'created', 'message' => 'Dry run — validated only.']);
                            continue;
                        }

                        $payload = [
                            'name' => $name,
                            'company_name' => $row['company_name'] ?? null,
                            'phone' => $this->safeNormalize($row['phone'] ?? null),
                            'whatsapp' => $this->safeNormalize($row['whatsapp'] ?? $row['phone'] ?? null),
                            'email' => $row['email'] ?? null,
                            'emirate' => $row['emirate'] ?? null,
                            'area' => $row['area'] ?? null,
                            'notes' => $row['notes'] ?? null,
                            'status' => $row['status'] ?? 'active',
                            'source' => 'historical_import',
                            'source_id' => !empty($row['source_id']) ? $row['source_id'] : null,
                        ];

                        if ($match) {
                            $match->update($payload);
                        } else {
                            $payload['customer_code'] = 'CUS-'.str_pad((string) (Customer::max('id') + 1), 5, '0', STR_PAD_LEFT);
                            Customer::create($payload);
                        }

                        $match ? $updated++ : $created++;
                        DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $row['source_id'] ?? null, 'label' => $name, 'outcome' => $match ? 'updated' : 'created']);
                    } catch (\Throwable $e) {
                        $errors++;
                        DataImportRow::create(['data_import_id' => $import->id, 'label' => $name, 'outcome' => 'error', 'message' => $e->getMessage()]);
                    }
                }
            });
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'updated_count' => $updated, 'skipped_count' => $skipped, 'conflict_count' => $conflicts, 'error_count' => $errors]);
        return $import;
    }

    /** @return array{0: Customer|null, 1: string|null} */
    private function findCustomerMatch(array $row): array
    {
        if ($phone = $this->safeNormalize($row['phone'] ?? null)) {
            if ($match = Customer::where('phone', $phone)->first()) {
                return [$match, 'phone'];
            }
        }
        if (!empty($row['email'])) {
            if ($match = Customer::where('email', $row['email'])->first()) {
                return [$match, 'email'];
            }
        }
        if (!empty($row['source_id'])) {
            if ($match = Customer::where('source_id', $row['source_id'])->first()) {
                return [$match, 'source_id'];
            }
        }
        return [null, null];
    }

    private function safeNormalize(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        try {
            return $this->phones->normalize($phone);
        } catch (\Throwable) {
            return null; // Invalid historical phone data — kept null rather than blocking the whole row.
        }
    }

    // ---------------------------------------------------------------
    // Historical orders — one row per item line, grouped by
    // source_order_number into one order with multiple lines.
    // ---------------------------------------------------------------

    /** Groups flat rows by source_order_number, keeping row order within each group. */
    private function groupOrderRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['source_order_number'] ?? ''));
            $groups[$key][] = $row;
        }
        return $groups;
    }

    public function previewOrders(array $rows): array
    {
        $groups = $this->groupOrderRows($rows);
        $preview = [];
        foreach ($groups as $sourceNumber => $lines) {
            if ($sourceNumber === '') {
                continue;
            }
            $existing = SalesOrder::where('source_order_number', $sourceNumber)->first();
            $isConflict = $existing && !$existing->source_order_number;
            $first = $lines[0];

            $itemsSubtotal = array_sum(array_map(fn ($l) => (float) ($l['item_price'] ?? $l['total'] ?? 0), $lines));
            $deliveryCharge = (float) ($first['delivery_charge'] ?? 0);
            $vatFromSource = $first['vat_amount'] ?? null;
            $totalPayable = $first['total_payable'] ?? $first['total'] ?? null;

            $reconciliation = $this->reconcileOrderTotals($itemsSubtotal, $deliveryCharge, $vatFromSource, $totalPayable);

            $dateEstimated = empty($first['order_date']);

            $preview[] = [
                'source_order_number' => $sourceNumber,
                'customer' => $first['customer_name'] ?? '',
                'line_count' => count($lines),
                'delivery_date' => $first['delivery_date'] ?? null,
                'items_subtotal' => round($itemsSubtotal, 2),
                'vat' => $reconciliation['vat'],
                'grand_total' => $reconciliation['grand_total'],
                'date_estimated' => $dateEstimated,
                'reconciliation_warning' => $reconciliation['warning'],
                'action' => $isConflict ? 'conflict' : ($existing ? 'update' : 'create'),
            ];
        }
        return ['rows' => $preview, 'total' => count($preview), 'source_row_count' => count($rows)];
    }

    /**
     * Reconciles item subtotal + delivery + VAT against a source "total
     * payable" figure. Never double-counts VAT: if a vat_amount is given,
     * it's used directly; otherwise it's derived as the difference
     * between total_payable and (items + delivery). Returns a warning
     * string (not an exception) when the reconciled total differs from
     * the source total by more than the tolerance — the caller decides
     * whether that blocks import.
     */
    private function reconcileOrderTotals(float $itemsSubtotal, float $deliveryCharge, $vatFromSource, $totalPayable): array
    {
        $base = round($itemsSubtotal + $deliveryCharge, 2);
        $warning = null;

        if ($vatFromSource !== null && $vatFromSource !== '') {
            $vat = round((float) $vatFromSource, 2);
            $grandTotal = round($base + $vat, 2);
            if ($totalPayable !== null && $totalPayable !== '') {
                $diff = abs($grandTotal - round((float) $totalPayable, 2));
                if ($diff > self::VAT_TOLERANCE) {
                    $warning = "Reconciled total (AED {$grandTotal}) differs from source Total Payables (AED {$totalPayable}) by AED ".round($diff, 2).'.';
                }
            }
        } elseif ($totalPayable !== null && $totalPayable !== '') {
            // No explicit VAT given — derive it from the total, but the
            // total always trivially matches itself once derived this
            // way, so what actually needs checking is whether the
            // IMPLIED VAT RATE is plausible (~0% or ~5%), not the total.
            $vat = round((float) $totalPayable - $base, 2);
            $grandTotal = round((float) $totalPayable, 2);
            $impliedRate = $base > 0 ? ($vat / $base) * 100 : ($vat == 0 ? 0 : 999);
            $closeToZero = abs($impliedRate - 0) <= 1.0;
            $closeToFivePercent = abs($impliedRate - 5) <= 1.0;
            if (!$closeToZero && !$closeToFivePercent) {
                $warning = "Total Payables (AED {$totalPayable}) implies a VAT of AED {$vat} on a base of AED {$base} (~".round($impliedRate, 1)."%) — not close to 0% or 5%. Please confirm this is correct.";
            }
        } else {
            $vat = 0.0;
            $grandTotal = $base;
        }

        return ['vat' => $vat, 'grand_total' => $grandTotal, 'warning' => $warning];
    }

    public function commitOrders(array $rows, int $userId, bool $isDryRun, ?SalesWorkflow $workflow = null): DataImport
    {
        $import = DataImport::create(['type' => 'orders', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $updated = $skipped = $conflicts = $errors = 0;
        $groups = $this->groupOrderRows($rows);

        foreach ($groups as $sourceNumber => $lines) {
            $first = $lines[0];
            $customerName = trim((string) ($first['customer_name'] ?? ''));

            if ($sourceNumber === '' || $customerName === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $customerName ?: null, 'outcome' => 'skipped', 'message' => 'Missing source order number or customer name.']);
                continue;
            }

            $invalidLine = null;
            foreach ($lines as $line) {
                $desc = trim((string) ($line['item_description'] ?? $line['description'] ?? ''));
                $qty = (float) ($line['item_qty'] ?? $line['qty'] ?? 1);
                $price = $line['item_price'] ?? $line['total'] ?? null;
                if ($desc === '' || $qty <= 0 || $price === null || $price === '') {
                    $invalidLine = "Invalid item line: description='{$desc}', qty={$qty}, price=".($price ?? 'missing');
                    break;
                }
            }
            if ($invalidLine) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $sourceNumber, 'label' => $customerName, 'outcome' => 'error', 'message' => $invalidLine]);
                continue;
            }

            try {
                $existing = SalesOrder::where('source_order_number', $sourceNumber)->first();
                if ($existing && !$existing->source_order_number) {
                    $conflicts++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $sourceNumber, 'label' => $customerName, 'outcome' => 'conflict', 'message' => "Order number collides with an existing ERP order (#{$existing->id}) not created by an import."]);
                    continue;
                }

                if ($isDryRun) {
                    $existing ? $updated++ : $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $sourceNumber, 'label' => $customerName, 'outcome' => $existing ? 'updated' : 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($lines, $first, $sourceNumber, $customerName, $existing, &$created, &$updated, $import) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $this->safeNormalize($first['customer_phone'] ?? null)],
                        ['name' => $customerName, 'customer_code' => 'CUS-'.str_pad((string) (Customer::max('id') + 1), 5, '0', STR_PAD_LEFT), 'status' => 'active', 'source' => 'historical_import']
                    );

                    $itemsSubtotal = array_sum(array_map(fn ($l) => (float) ($l['item_price'] ?? $l['total'] ?? 0), $lines));
                    $deliveryCharge = (float) ($first['delivery_charge'] ?? 0);
                    $reconciliation = $this->reconcileOrderTotals($itemsSubtotal, $deliveryCharge, $first['vat_amount'] ?? null, $first['total_payable'] ?? $first['total'] ?? null);
                    $subtotal = round($itemsSubtotal + $deliveryCharge, 2);
                    $vat = $reconciliation['vat'];
                    $grandTotal = $reconciliation['grand_total'];

                    $dateEstimated = empty($first['order_date']);
                    $orderDate = !empty($first['order_date']) ? \Carbon\Carbon::parse($first['order_date']) : now()->startOfMonth();
                    $notes = trim((string) ($first['notes'] ?? ''));
                    if ($dateEstimated) {
                        $notes = trim($notes.' [Migration note: exact order date unknown — set to the 1st of the source month.]');
                    }

                    $orderPayload = [
                        'source_order_number' => $sourceNumber,
                        'customer_id' => $customer->id,
                        'order_date' => $orderDate,
                        'order_month' => $orderDate->copy()->startOfMonth(),
                        'delivery_date' => $first['delivery_date'] ?? null,
                        'emirate' => $first['emirate'] ?? null,
                        'confirmation_status' => $first['confirmation_status'] ?? 'confirmed',
                        'design_status' => $first['design_status'] ?? 'not_required',
                        'production_status' => $first['production_status'] ?? 'not_required',
                        'delivery_status' => !empty($first['delivery_date']) ? 'delivered' : 'not_scheduled',
                        'payment_status' => $first['payment_status'] ?? 'unpaid',
                        'subtotal' => $subtotal,
                        'tax_total' => $vat,
                        'grand_total' => $grandTotal,
                        'notes' => $notes ?: null,
                        'is_legacy_delivery_import' => true,
                    ];

                    if ($existing) {
                        $existing->update($orderPayload);
                        $order = $existing;
                        $order->items()->delete();
                        $updated++;
                    } else {
                        $orderPayload['order_number'] = 'LEG-'.$sourceNumber;
                        $order = SalesOrder::create($orderPayload);
                        $created++;
                    }

                    foreach ($lines as $line) {
                        $desc = trim((string) ($line['item_description'] ?? $line['description'] ?? 'Imported item'));
                        $qty = (float) ($line['item_qty'] ?? $line['qty'] ?? 1);
                        $lineTotal = (float) ($line['item_price'] ?? $line['total'] ?? 0);
                        SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => $desc, 'qty' => $qty, 'unit_price' => $qty > 0 ? round($lineTotal / $qty, 4) : $lineTotal, 'line_total' => $lineTotal]);
                    }
                    if ($deliveryCharge > 0) {
                        SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => 'Delivery charge', 'qty' => 1, 'unit_price' => $deliveryCharge, 'line_total' => $deliveryCharge]);
                    }

                    $invoice = $order->invoices()->first();
                    $paidAmount = min($grandTotal, max(0, (float) ($first['paid_amount'] ?? (($first['payment_status'] ?? null) === 'paid' ? $grandTotal : 0))));
                    $invoiceStatus = $paidAmount >= $grandTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'sent');
                    $invoicePayload = [
                        'customer_id' => $customer->id, 'sales_order_id' => $order->id, 'invoice_date' => $orderDate,
                        'status' => $invoiceStatus, 'subtotal' => $subtotal, 'tax_total' => $vat, 'grand_total' => $grandTotal,
                        'amount_paid' => $paidAmount, 'outstanding_amount' => round($grandTotal - $paidAmount, 2),
                    ];
                    if ($invoice) {
                        $invoice->update($invoicePayload);
                        $invoice->items()->delete();
                    } else {
                        $invoicePayload['invoice_number'] = 'INV-'.str_replace('LEG-', '', $order->order_number ?? $sourceNumber);
                        $invoice = Invoice::create($invoicePayload);
                    }
                    foreach ($lines as $line) {
                        $desc = trim((string) ($line['item_description'] ?? $line['description'] ?? 'Imported item'));
                        $qty = (float) ($line['item_qty'] ?? $line['qty'] ?? 1);
                        $lineTotal = (float) ($line['item_price'] ?? $line['total'] ?? 0);
                        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $desc, 'qty' => $qty, 'rate' => $qty > 0 ? round($lineTotal / $qty, 4) : $lineTotal, 'line_total' => $lineTotal]);
                    }

                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $sourceNumber, 'label' => $customerName, 'outcome' => $existing ? 'updated' : 'created']);
                });
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $sourceNumber, 'label' => $customerName, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'updated_count' => $updated, 'skipped_count' => $skipped, 'conflict_count' => $conflicts, 'error_count' => $errors]);
        return $import;
    }

    // ---------------------------------------------------------------
    // Current / active orders — become real, live orders in today's
    // workflow, with a genuine SalesOrderItem, ProductionJob, and
    // Invoice, going through SimpleWorkflowService so cascading rules
    // apply as they would for an order created by hand.
    // ---------------------------------------------------------------

    public function previewCurrentOrders(array $rows): array
    {
        $preview = [];
        foreach ($rows as $row) {
            $manualRef = trim((string) ($row['manual_reference'] ?? ''));
            $orderNumber = $manualRef !== '' ? strtoupper($manualRef).'-'.now()->format('my') : null;
            $existing = $orderNumber ? SalesOrder::where('order_number', $orderNumber)->exists() : false;
            $preview[] = [
                'manual_reference' => $manualRef,
                'customer' => $row['customer_name'] ?? '',
                'delivery_date' => $row['delivery_date'] ?? null,
                'action' => $existing ? 'conflict' : 'create',
            ];
        }
        return ['rows' => $preview, 'total' => count($preview)];
    }

    public function commitCurrentOrders(array $rows, int $userId, bool $isDryRun, SalesWorkflow $workflow, SimpleWorkflowService $simpleWorkflow): DataImport
    {
        $import = DataImport::create(['type' => 'current_orders', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $updated = $skipped = $conflicts = $errors = 0;

        foreach ($rows as $row) {
            $manualRef = strtoupper(trim((string) ($row['manual_reference'] ?? '')));
            $customerName = trim((string) ($row['customer_name'] ?? ''));
            $orderNumber = null;

            if ($manualRef === '' || $customerName === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $customerName ?: null, 'outcome' => 'skipped', 'message' => 'Missing required manual_reference or customer_name.']);
                continue;
            }

            try {
                $orderDate = !empty($row['order_date']) ? \Carbon\Carbon::parse($row['order_date']) : now();
                $orderNumber = $manualRef.'-'.$orderDate->format('my');

                if (SalesOrder::where('order_number', $orderNumber)->exists()) {
                    $conflicts++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $orderNumber, 'label' => $customerName, 'outcome' => 'conflict', 'message' => "Order number {$orderNumber} already exists — manual_reference must be unique within this month."]);
                    continue;
                }

                if ($isDryRun) {
                    $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $orderNumber, 'label' => $customerName, 'outcome' => 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($row, $orderNumber, $manualRef, $customerName, $orderDate, $workflow, $simpleWorkflow, $import) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $this->safeNormalize($row['customer_phone'] ?? null)],
                        ['name' => $customerName, 'customer_code' => 'CUS-'.str_pad((string) (Customer::max('id') + 1), 5, '0', STR_PAD_LEFT), 'status' => 'active', 'source' => 'historical_import']
                    );

                    $qty = max(0.01, (float) ($row['qty'] ?? 1));
                    $unitPrice = max(0, (float) ($row['unit_price'] ?? 0));
                    $taxRate = (float) ($row['tax_rate'] ?? 5);
                    $lineSubtotal = round($qty * $unitPrice, 2);
                    $taxAmount = round($lineSubtotal * $taxRate / 100, 2);
                    $grandTotal = round($lineSubtotal + $taxAmount, 2);

                    $order = SalesOrder::create([
                        'order_number' => $orderNumber, 'manual_reference' => $manualRef,
                        'order_month' => $orderDate->copy()->startOfMonth(), 'customer_id' => $customer->id,
                        'order_date' => $orderDate, 'delivery_date' => $row['delivery_date'] ?? null,
                        'emirate' => $row['emirate'] ?? $customer->emirate,
                        'confirmation_status' => 'waiting', 'design_status' => 'need_design', 'production_status' => 'waiting',
                        'delivery_status' => !empty($row['delivery_date']) ? 'scheduled' : 'not_scheduled',
                        'payment_status' => 'unpaid', 'subtotal' => $lineSubtotal, 'tax_total' => $taxAmount, 'grand_total' => $grandTotal,
                        'notes' => $row['notes'] ?? null, 'is_legacy_delivery_import' => false,
                    ]);

                    SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => $row['description'] ?? 'Imported order', 'qty' => $qty, 'unit_price' => $unitPrice, 'tax_amount' => $taxAmount, 'line_total' => $grandTotal]);

                    ProductionJob::create(['job_number' => app(NumberingService::class)->next('production_job'), 'sales_order_id' => $order->id, 'due_date' => $order->delivery_date, 'stage' => 'waiting_for_design', 'sale_value' => $grandTotal, 'estimated_profit' => $grandTotal]);

                    $paidAmount = min($grandTotal, max(0, (float) ($row['paid_amount'] ?? 0)));
                    $invoiceStatus = $paidAmount >= $grandTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'sent');
                    $invoice = Invoice::create([
                        'invoice_number' => app(NumberingService::class)->next('invoice'), 'customer_id' => $customer->id, 'sales_order_id' => $order->id,
                        'invoice_date' => $orderDate, 'status' => $invoiceStatus, 'subtotal' => $lineSubtotal, 'tax_total' => $taxAmount,
                        'grand_total' => $grandTotal, 'amount_paid' => $paidAmount, 'outstanding_amount' => round($grandTotal - $paidAmount, 2),
                    ]);
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $row['description'] ?? 'Imported order', 'qty' => $qty, 'rate' => $unitPrice, 'tax_amount' => $taxAmount, 'line_total' => $grandTotal]);
                    $order->update(['payment_status' => $invoiceStatus === 'paid' ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid')]);

                    $status = in_array($row['status'] ?? null, SimpleWorkflowService::STATUSES, true) ? $row['status'] : 'pending';
                    $simpleWorkflow->setStatus($order, $status);
                    if ($status === 'pending') {
                        $confirmation = in_array($row['confirmation'] ?? null, SimpleWorkflowService::CONFIRMATIONS, true) ? $row['confirmation'] : 'not_confirmed';
                        $simpleWorkflow->setConfirmation($order, $confirmation);
                        $design = in_array($row['design'] ?? null, SimpleWorkflowService::DESIGNS, true) ? $row['design'] : 'need_designer';
                        $simpleWorkflow->setDesign($order, $design);
                    }

                    if ($order->fresh()->delivery_date) {
                        $workflow->createDelivery($order->fresh());
                    }

                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $orderNumber, 'label' => $customerName, 'outcome' => 'created']);
                });
                $created++;
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $orderNumber, 'label' => $customerName, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'updated_count' => $updated, 'skipped_count' => $skipped, 'conflict_count' => $conflicts, 'error_count' => $errors]);
        return $import;
    }
}
