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

    public function __construct(private PhoneNormalizer $phones, private \App\Services\AccountingService $accounting, private \App\Services\NumberingService $numbers) {}

    /**
     * Posts the real Debit AR / Credit Sales Revenue (+ VAT Output) journal
     * entry for an invoice — the exact same accounts SalesWorkflow::
     * orderToInvoice() uses for a manually-created order, so an imported
     * order's revenue genuinely appears in Income/P&L reports rather than
     * only existing as an Invoice record. Guarded against double-posting:
     * checks for an existing entry first, since re-importing the same
     * order must never post the same revenue twice.
     */
    private function postInvoiceIfNotAlready(Invoice $invoice): void
    {
        if (\App\Models\JournalEntry::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->exists()) {
            return;
        }
        $lines = [
            ['account' => '1100', 'debit' => (float) $invoice->grand_total, 'credit' => 0],
            ['account' => '4000', 'debit' => 0, 'credit' => (float) $invoice->subtotal],
        ];
        if ((float) $invoice->tax_total > 0) {
            $lines[] = ['account' => '2100', 'debit' => 0, 'credit' => (float) $invoice->tax_total];
        }
        $this->accounting->post($invoice, "Invoice {$invoice->invoice_number}", $lines, (string) $invoice->invoice_date);
    }

    /**
     * Creates a real Payment (+ allocation) and posts the matching Debit
     * Cash/Bank / Credit AR entry — the same pattern SalesWorkflow::
     * recordPayment() uses — so an imported "paid" order's cash actually
     * shows up in Bank/Cash Reconciliation and Cashflow, not just as a
     * number on the Invoice record. Guarded the same way: does nothing if
     * a payment already exists for this invoice (re-import safe).
     */
    private function postPaymentIfNotAlready(Invoice $invoice, float $amount, string $date, string $method = 'bank'): void
    {
        if ($amount <= 0 || \App\Models\Payment::whereHas('allocations', fn ($q) => $q->where('invoice_id', $invoice->id))->exists()) {
            return;
        }
        $payment = \App\Models\Payment::create([
            'payment_number' => $this->numbers->next('payment'), 'customer_id' => $invoice->customer_id,
            'method' => $method, 'amount' => $amount, 'payment_date' => $date, 'received_by' => auth()->id(),
        ]);
        $payment->allocations()->create(['invoice_id' => $invoice->id, 'allocated_amount' => $amount]);
        $cashAccount = in_array($method, ['cash', 'cod'], true) ? '1000' : '1010';
        $this->accounting->post($payment, "Payment {$payment->payment_number}", [
            ['account' => $cashAccount, 'debit' => $amount, 'credit' => 0],
            ['account' => '1100', 'debit' => 0, 'credit' => $amount],
        ], $date);
    }

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
        $rawHeaders = fgetcsv($handle) ?: [];
        if (isset($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]); // strip a leading UTF-8 BOM
        }
        // Blank headers get unique placeholder names; otherwise two blank
        // columns collapse into one key and the second silently wipes the
        // first (this is exactly how a header-less customer-name column
        // was lost).
        $headers = [];
        foreach ($rawHeaders as $index => $header) {
            $name = $this->normalizeHeader((string) $header);
            if ($name === '' || isset($headers[$name])) {
                $name = '__blank_'.$index;
            }
            $headers[$name] = $index;
        }
        $headers = array_keys($headers);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            if (trim(implode('', array_map(fn ($v) => (string) $v, $row))) === '') {
                continue; // fully empty spacer row
            }
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);
        return $rows;
    }

    /** Lower-cases headers and maps common spellings to the template names. */
    private function normalizeHeader(string $header): string
    {
        $key = strtolower(trim($header));
        $key = trim(preg_replace('/[^a-z0-9#]+/', '_', $key), '_');
        return match ($key) {
            'customer', 'client', 'client_name', 'customer_full_name' => 'customer_name',
            'mobile', 'phone_number', 'contact', 'contact_number', 'whatsapp_number' => $key === 'whatsapp_number' ? 'whatsapp' : 'customer_phone',
            'order_no', 'order_#', '#', 'ref', 'order_ref' => 'source_order_number',
            default => $key,
        };
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

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const EMIRATES = [
        'abudhabi' => 'Abu Dhabi', 'abu dhabi' => 'Abu Dhabi', 'auh' => 'Abu Dhabi', 'ad' => 'Abu Dhabi',
        'al ain' => 'Al Ain', 'alain' => 'Al Ain',
        'dubai' => 'Dubai', 'dxb' => 'Dubai',
        'sharjah' => 'Sharjah', 'shj' => 'Sharjah',
        'ajman' => 'Ajman',
        'rak' => 'Ras Al Khaimah', 'ras al khaimah' => 'Ras Al Khaimah', 'ras alkhaimah' => 'Ras Al Khaimah',
        'fujairah' => 'Fujairah', 'fujeirah' => 'Fujairah',
        'uaq' => 'Umm Al Quwain', 'umq' => 'Umm Al Quwain', 'umm al quwain' => 'Umm Al Quwain',
    ];

    /**
     * Turns the raw CSV rows of a real-world order sheet into clean,
     * validated order groups. Preview, dry run and commit ALL use this,
     * so what the preview says is exactly what the import will do.
     *
     * Handles the sheet layout the team actually uses:
     *  - only the first line of an order carries the order number; the
     *    lines below it (blank number) are more items of the same order;
     *  - the customer-name column may have no header;
     *  - "Delivery 16 July 🚨", "Pickup 3 July", "Balance : 293 AED",
     *    "Paidfully", "WhatsApp : 05x / Call : 05x", "AbuDhabi - MBZ - Villa 3".
     *
     * $sheetMonth (Y-m) is the month the sheet belongs to. Order numbers
     * restart every month, so it is also added to the stored order key —
     * otherwise August's order #1 would overwrite July's order #1.
     */
    public function prepareOrders(array $rows, ?string $sheetMonth = null): array
    {
        $month = $this->parseSheetMonth($sheetMonth);
        $rows = $this->applyOrderColumnFallbacks($rows);

        $groups = [];
        $current = null;
        $ignoredRows = 0;
        $orphanRows = 0;
        foreach ($rows as $row) {
            $number = trim((string) ($row['source_order_number'] ?? ''));
            if ($number !== '') {
                $current = $number;
                $groups[$number][] = $row;
                continue;
            }
            if (!$this->rowHasItemData($row)) {
                $ignoredRows++;
                continue;
            }
            if ($current === null) {
                $orphanRows++;
                continue;
            }
            $groups[$current][] = $row; // continuation line of the order above
        }

        $orders = [];
        foreach ($groups as $number => $lines) {
            $orders[] = $this->buildOrder((string) $number, $lines, $month);
        }

        return ['orders' => $orders, 'ignored_rows' => $ignoredRows, 'orphan_rows' => $orphanRows, 'sheet_month' => $month?->format('Y-m')];
    }

    private function parseSheetMonth(?string $sheetMonth): ?\Carbon\Carbon
    {
        if (!$sheetMonth || !preg_match('/^\d{4}-\d{2}$/', $sheetMonth)) {
            return null;
        }
        return \Carbon\Carbon::createFromFormat('Y-m-d', $sheetMonth.'-01')->startOfDay();
    }

    /** A blank-header column holding names is the customer-name column. */
    private function applyOrderColumnFallbacks(array $rows): array
    {
        $hasName = false;
        foreach ($rows as $row) {
            if (trim((string) ($row['customer_name'] ?? '')) !== '') {
                $hasName = true;
                break;
            }
        }
        if ($hasName || $rows === []) {
            return $rows;
        }

        $nameColumn = null;
        foreach (array_keys($rows[0]) as $key) {
            if (!str_starts_with((string) $key, '__blank_')) {
                continue;
            }
            foreach ($rows as $row) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '' && preg_match('/\pL/u', $value)) {
                    $nameColumn = $key;
                    break 2;
                }
            }
        }
        if ($nameColumn === null) {
            return $rows;
        }
        return array_map(function ($row) use ($nameColumn) {
            $row['customer_name'] = $row[$nameColumn] ?? null;
            return $row;
        }, $rows);
    }

    private function rowHasItemData(array $row): bool
    {
        foreach (['item_description', 'description', 'item_qty', 'qty', 'item_price', 'total'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function buildOrder(string $number, array $lines, ?\Carbon\Carbon $month): array
    {
        // Order-level values can sit on ANY line of the order in the real
        // sheet (the delivery date is often on the last item line), so
        // take the first non-empty value of each column across the order.
        $first = [];
        foreach ($lines as $line) {
            foreach ($line as $key => $value) {
                if (!isset($first[$key]) || trim((string) $first[$key]) === '') {
                    $first[$key] = $value;
                }
            }
        }
        $problems = [];
        $warnings = [];
        $notes = [];

        // Customer + phone
        $phones = $this->extractPhones((string) ($first['customer_phone'] ?? ''));
        $phone = $phones[0] ?? null;
        if (count($phones) > 1) {
            $notes[] = 'Other phone: '.implode(', ', array_slice($phones, 1));
        }
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($first['customer_name'] ?? '')));
        if ($name === '' || !preg_match('/[\pL\pN]/u', $name)) {
            if ($phone) {
                $name = 'Customer '.$phone;
                $warnings[] = 'No customer name — saved as "'.$name.'".';
            } else {
                $problems[] = 'No customer name and no valid phone number.';
            }
        }
        if (!$phone && trim((string) ($first['customer_phone'] ?? '')) !== '') {
            $warnings[] = 'Phone "'.trim((string) $first['customer_phone']).'" is not a valid number — saved without phone.';
        }

        // Item lines
        $items = [];
        $zeroLines = [];
        foreach ($lines as $line) {
            $desc = trim(preg_replace('/[\s\x{2060}\x{200B}]+/u', ' ', (string) ($line['item_description'] ?? $line['description'] ?? '')));
            $qtyRaw = trim((string) ($line['item_qty'] ?? $line['qty'] ?? ''));
            $price = $this->parseMoney($line['item_price'] ?? $line['total'] ?? null);
            $qty = $qtyRaw === '' ? 1.0 : (float) ($this->parseMoney($qtyRaw) ?? 1);
            if ($desc === '' && $price === null) {
                continue;
            }
            $desc = $desc === '' ? 'Imported item' : $desc;
            if ($qty <= 0 && (float) $price == 0.0) {
                $zeroLines[] = $desc; // quantity 0 and no price: not part of the order
                continue;
            }
            if ($qty <= 0) {
                $warnings[] = "\"{$desc}\" has quantity 0 but a price — imported as quantity 1.";
                $qty = 1.0;
            }
            if ($price === null) {
                $warnings[] = "\"{$desc}\" has no price — imported at AED 0 (included in the order).";
                $price = 0.0;
            }
            $items[] = ['description' => $desc, 'qty' => $qty, 'line_total' => round($price, 2)];
        }
        if ($items === [] && $zeroLines !== []) {
            foreach ($zeroLines as $desc) {
                $items[] = ['description' => $desc, 'qty' => 1.0, 'line_total' => 0.0];
            }
            $warnings[] = 'All lines have quantity 0 — imported as a zero-value order.';
        } elseif ($zeroLines !== []) {
            $warnings[] = 'Left out lines with quantity 0: '.implode(', ', $zeroLines).'.';
        }
        if ($items === []) {
            $problems[] = 'No item lines found.';
        }

        // Delivery / pickup + address
        $deliveryRaw = trim((string) ($first['delivery_date'] ?? ''));
        $delivery = $this->parseLooseDate($deliveryRaw, $month);
        if ($deliveryRaw !== '' && !$delivery) {
            $warnings[] = 'Delivery date "'.$deliveryRaw.'" could not be read — kept in notes.';
        }
        if ($deliveryRaw !== '' && (!$delivery || $this->hasExtraDeliveryText($deliveryRaw))) {
            $notes[] = 'Source delivery: '.$this->stripEmoji($deliveryRaw);
        }
        [$emirate, $address, $isPickupAddress] = $this->parseLocation((string) ($first['emirate'] ?? ''), (string) ($first['delivery_address'] ?? $first['address'] ?? ''));
        $isPickup = $isPickupAddress || preg_match('/pick\s*-?\s*up/i', $deliveryRaw);

        // Order date
        $orderDateRaw = trim((string) ($first['order_date'] ?? ''));
        $orderDate = $orderDateRaw !== '' ? $this->parseLooseDate($orderDateRaw, $month) : null;
        $dateEstimated = false;
        if (!$orderDate) {
            $dateEstimated = true;
            $orderDate = $month?->copy() ?? $delivery?->copy()->startOfMonth() ?? now()->startOfMonth();
        }

        // Money
        [$items, $deliveryCharge, $vat, $grandTotal, $moneyWarnings] = $this->resolveOrderMoney($items, $lines, $first);
        array_push($warnings, ...$moneyWarnings);
        $itemsSubtotal = round(array_sum(array_column($items, 'line_total')), 2);

        [$paid, $paymentMethod, $paymentNote] = $this->parsePayment($first, $grandTotal);
        if ($paymentNote && $grandTotal > 0) {
            $warnings[] = $paymentNote;
        }
        if ($grandTotal <= 0) {
            $warnings[] = 'Order total is AED 0 — saved as a record only (no revenue posted).';
        }
        $paymentStatus = $paid >= $grandTotal ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');

        // Workflow statuses
        $statusText = strtolower(implode(' ', array_map(fn ($k) => (string) ($first[$k] ?? ''), ['confirmation_status', 'design_status', 'delivery_status', 'status'])));
        $cancelled = (bool) preg_match('/cancel|return/', $statusText);
        $delivered = !$cancelled && (str_contains($statusText, 'deliver') || str_contains($statusText, 'picked') || ($delivery && $delivery->lte(now())));

        $noteText = trim((string) ($first['notes'] ?? ''));
        if ($noteText !== '') {
            array_unshift($notes, $noteText);
        }
        if ($dateEstimated) {
            $notes[] = '[Migration note: exact order date unknown — set to '.$orderDate->format('j M Y').'.]';
        }

        $key = $month ? $month->format('Ym').'-'.$number : $number;

        return [
            'source_number' => $number,
            'key' => $key,
            'customer_name' => $name,
            'phone' => $phone,
            'items' => $items,
            'line_count' => count($items),
            'order_date' => $orderDate,
            'date_estimated' => $dateEstimated,
            'delivery_date' => $delivery,
            'delivery_raw' => $deliveryRaw,
            'fulfillment_type' => $isPickup ? 'pickup' : 'delivery',
            'emirate' => $emirate,
            'address' => $address,
            'items_subtotal' => $itemsSubtotal,
            'delivery_charge' => round($deliveryCharge, 2),
            'vat' => $vat,
            'grand_total' => $grandTotal,
            'paid' => $paid,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'notes' => $notes ? implode("\n", $notes) : null,
            'problems' => $problems,
            'warnings' => $warnings,
        ];
    }

    /**
     * Works out delivery, VAT and total the way the order sheet is really
     * filled in. Two layouts appear in the same file:
     *  A) delivery_charge + VAT amount filled, no total (e.g. 40.00 / 16.50);
     *  B) VAT as a rate ("5%") or blank, delivery blank, and only the final
     *     "total payable" (items + 5% VAT + delivery, rounded).
     * For B, VAT is items x rate and the rest of the total is the delivery
     * charge, so the imported total always equals what the customer paid.
     * Returns [items, delivery, vat, grand total, warnings].
     */
    private function resolveOrderMoney(array $items, array $lines, array $first): array
    {
        $warnings = [];
        $itemsSubtotal = round(array_sum(array_column($items, 'line_total')), 2);
        $deliveryGiven = $this->parseMoney($first['delivery_charge'] ?? null);

        $vatCell = trim((string) ($first['vat_amount'] ?? ''));
        $vatRate = null;
        $vatAmount = null;
        if (str_contains($vatCell, '%')) {
            $vatRate = (float) $this->parseMoney($vatCell) / 100;
        } elseif ($vatCell !== '' && (float) $this->parseMoney($vatCell) > 0) {
            $vatAmount = round((float) $this->parseMoney($vatCell), 2);
        }

        // An order can carry more than one total (an add-on written under
        // the same order number) — they add up.
        $total = null;
        foreach ($lines as $line) {
            $value = $this->parseMoney($line['total_payable'] ?? $line['total_amount'] ?? null);
            if ($value !== null) {
                $total = round(($total ?? 0) + $value, 2);
            }
        }

        if ($total === null) {
            $delivery = round((float) ($deliveryGiven ?? 0), 2);
            $vat = $vatAmount ?? round($itemsSubtotal * ($vatRate ?? 0), 2);
            return [$items, $delivery, $vat, round($itemsSubtotal + $delivery + $vat, 2), $warnings];
        }

        $rate = $vatRate ?? 0.05; // UAE standard rate when the sheet shows only a total

        // Package price: no item prices at all, only a total.
        if ($itemsSubtotal == 0.0 && $total > 0 && $items !== [] && $deliveryGiven === null && $vatAmount === null) {
            $net = round($total / (1 + $rate), 2);
            $items[0]['line_total'] = $net;
            $warnings[] = 'Items have no prices — the total AED '.number_format($total, 2).' was imported as one package price on "'.$items[0]['description'].'".';
            return [$items, 0.0, round($total - $net, 2), $total, $warnings];
        }

        $vat = $vatAmount ?? round($itemsSubtotal * $rate, 2);
        if ($deliveryGiven !== null) {
            $delivery = round($deliveryGiven, 2);
            $computed = round($itemsSubtotal + $delivery + $vat, 2);
            if (abs($computed - $total) > 1.0) {
                $warnings[] = 'Items + delivery + VAT = AED '.number_format($computed, 2).' but the sheet total is AED '.number_format($total, 2).' — imported at AED '.number_format($computed, 2).'.';
            }
            return [$items, $delivery, $vat, $computed, $warnings];
        }

        $delivery = round($total - $itemsSubtotal - $vat, 2);
        if ($delivery > 0 && $delivery < 5) {
            // Too small to be a delivery fee: the sheet rounded the total up.
            $items[] = ['description' => 'Rounding adjustment', 'qty' => 1.0, 'line_total' => $delivery];
            return [$items, 0.0, $vat, $total, $warnings];
        }
        if ($delivery < 0) {
            // Total is below items + VAT: a discount. Keep the sheet total
            // (what was actually charged) and take VAT out of it.
            $vat = round($total - $total / (1 + $rate), 2);
            $warnings[] = 'Sheet total AED '.number_format($total, 2).' is less than items + VAT — treated as a discount of AED '.number_format(abs($delivery), 2).'.';
            $items[] = ['description' => 'Discount', 'qty' => 1.0, 'line_total' => round($total - $vat - $itemsSubtotal, 2)];
            return [$items, 0.0, $vat, $total, $warnings];
        }
        return [$items, $delivery, $vat, $total, $warnings];
    }

    /** All valid phone numbers in a cell like "WhatsApp : 05x /Call : 05x". */
    public function extractPhones(string $raw): array
    {
        $found = [];
        foreach (preg_split('/[\/,;|\n]|\bor\b/i', $raw) as $chunk) {
            if (!preg_match('/\+?[\d][\d\s\-()]{6,}\d/', $chunk, $m)) {
                continue;
            }
            $normalized = $this->safeNormalize($m[0]);
            if ($normalized && !in_array($normalized, $found, true)) {
                $found[] = $normalized;
            }
        }
        return $found;
    }

    private function parseMoney($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $value));
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        return (float) $clean;
    }

    private function stripEmoji(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2060}\x{200B}]/u', '', $text)));
    }

    /**
     * Reads "2026-07-16", "16/07/2026", "Delivery 16 July", "3rd July",
     * "6-7 July" (first day), "Monday 13 July", "by 15 July 🚨". A date with
     * no year takes the sheet's year; a month well before the sheet month
     * (Dec sheet, "5 January" delivery) rolls into the next year.
     */
    public function parseLooseDate(string $raw, ?\Carbon\Carbon $month = null): ?\Carbon\Carbon
    {
        $text = $this->stripEmoji($raw);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return \Carbon\Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }
        if (preg_match('/\b(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})\b/', $text, $m)) {
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $year)) {
                return \Carbon\Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();
            }
        }
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?(?:\s*[-–&]\s*\d{1,2}(?:st|nd|rd|th)?)?\s*(?:of\s+)?(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?(?:\s*,?\s*(\d{4}))?/i', $text, $m)) {
            $day = (int) $m[1];
            $mon = self::MONTHS[strtolower(substr($m[2], 0, 3))];
            $year = !empty($m[3]) ? (int) $m[3] : ($month?->year ?? now()->year);
            if (empty($m[3]) && $month && $month->month - $mon >= 6) {
                $year++;
            }
            if (checkdate($mon, $day, $year)) {
                return \Carbon\Carbon::create($year, $mon, $day)->startOfDay();
            }
        }
        return null;
    }

    /** True when the delivery cell says more than just the date (🚨, "Sharp", "or earlier", ...). */
    private function hasExtraDeliveryText(string $raw): bool
    {
        $rest = strtolower($this->stripEmoji($raw));
        $rest = preg_replace('/\b(\d{1,2})(st|nd|rd|th)?\b|\b\d{4}\b|[\/.\-–,:]/', ' ', $rest);
        $rest = preg_replace('/\b(delivery|deliver|pick\s*up|pickup|date|on|monday|tuesday|wednesday|thursday|friday|saturday|sunday|jan\w*|feb\w*|mar\w*|apr\w*|may|jun\w*|jul\w*|aug\w*|sep\w*|oct\w*|nov\w*|dec\w*)\b/', ' ', $rest);
        return trim($rest) !== '';
    }

    /** "AbuDhabi - MBZ - Villa 345" → ['Abu Dhabi', full address, pickup?]. */
    private function parseLocation(string $emirateCell, string $addressCell): array
    {
        $cell = trim(preg_replace('/\s+/u', ' ', $emirateCell));
        $address = trim(preg_replace('/\s+/u', ' ', $addressCell));
        if ($cell === '' && $address === '') {
            return [null, null, false];
        }
        if (preg_match('/pick\s*-?\s*up/i', $cell) && !str_contains($cell, '-')) {
            return [null, $address ?: null, true];
        }
        $firstPart = trim(preg_split('/\s*[-–,\/]\s*/u', $cell)[0] ?? '');
        $lookup = strtolower($firstPart);
        $emirate = self::EMIRATES[$lookup] ?? self::EMIRATES[str_replace(' ', '', $lookup)] ?? null;
        if (!$emirate) {
            foreach (self::EMIRATES as $alias => $name) {
                if (strlen($alias) > 3 && str_starts_with($lookup, $alias)) {
                    $emirate = $name;
                    break;
                }
            }
        }
        $emirate ??= $firstPart !== '' ? mb_substr($firstPart, 0, 60) : null;
        $fullAddress = $address !== '' ? $address : (str_contains($cell, '-') ? $cell : null);
        return [$emirate, $fullAddress, false];
    }

    /** Returns [paid amount, payment method, warning|null]. */
    private function parsePayment(array $first, float $grandTotal): array
    {
        $text = strtolower(trim((string) ($first['payment_status'] ?? '')));
        $method = preg_match('/tabby|tamara|online|link/', $text) ? 'online' : (str_contains($text, 'cash') ? 'cash' : 'bank');
        $explicit = $this->parseMoney($first['paid_amount'] ?? null);
        if ($explicit !== null) {
            return [round(min($grandTotal, max(0, $explicit)), 2), $method, null];
        }
        if ($text === '') {
            return [0.0, $method, 'No payment status — imported as unpaid.'];
        }
        if (preg_match('/balance\s*:?\s*(?:aed)?\s*([\d,.]+)/', $text, $m)) {
            $balance = (float) str_replace(',', '', $m[1]);
            if ($balance > $grandTotal) {
                return [0.0, $method, "Balance AED {$balance} is more than the order total AED {$grandTotal} — imported as unpaid."];
            }
            return [round($grandTotal - $balance, 2), $method, null];
        }
        if (preg_match('/pending|unpaid|not\s*paid|due/', $text)) {
            return [0.0, $method, null];
        }
        if (preg_match('/(deposit|advance|partial)\D*([\d,.]+)/', $text, $m)) {
            return [round(min($grandTotal, (float) str_replace(',', '', $m[2])), 2), $method, null];
        }
        if (preg_match('/paid|tabby|tamara|online payment|received|settled/', $text)) {
            return [$grandTotal, $method, null];
        }
        return [0.0, $method, 'Payment status "'.trim((string) $first['payment_status']).'" not recognised — imported as unpaid.'];
    }

    public function previewOrders(array $rows, ?string $sheetMonth = null): array
    {
        $prepared = $this->prepareOrders($rows, $sheetMonth);
        $preview = [];
        foreach ($prepared['orders'] as $order) {
            $existing = SalesOrder::where('source_order_number', $order['key'])->first();
            $action = $order['problems'] ? 'skip' : ($existing ? 'update' : 'create');
            $preview[] = [
                'source_order_number' => $order['source_number'],
                'customer' => $order['customer_name'],
                'phone' => $order['phone'],
                'line_count' => $order['line_count'],
                'delivery_date' => $order['delivery_date']?->format('d M Y'),
                'fulfillment_type' => $order['fulfillment_type'],
                'emirate' => $order['emirate'],
                'items_subtotal' => $order['items_subtotal'],
                'vat' => $order['vat'],
                'grand_total' => $order['grand_total'],
                'paid' => $order['paid'],
                'date_estimated' => $order['date_estimated'],
                'reconciliation_warning' => null,
                'issues' => array_merge($order['problems'], $order['warnings']),
                'action' => $action,
            ];
        }
        $counts = array_count_values(array_column($preview, 'action'));
        return [
            'rows' => $preview,
            'total' => count($preview),
            'source_row_count' => count($rows),
            'ignored_rows' => $prepared['ignored_rows'],
            'orphan_rows' => $prepared['orphan_rows'],
            'sheet_month' => $prepared['sheet_month'],
            'counts' => $counts,
            'grand_total' => round(array_sum(array_column($preview, 'grand_total')), 2),
            'paid_total' => round(array_sum(array_column($preview, 'paid')), 2),
        ];
    }

    public function commitOrders(array $rows, int $userId, bool $isDryRun, ?SalesWorkflow $workflow = null, ?string $sheetMonth = null): DataImport
    {
        $import = DataImport::create(['type' => 'orders', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $updated = $skipped = $conflicts = $errors = 0;
        $prepared = $this->prepareOrders($rows, $sheetMonth);

        foreach ($prepared['orders'] as $order) {
            $key = $order['key'];
            $label = $order['customer_name'] ?: null;

            if ($order['problems']) {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $key, 'label' => $label, 'outcome' => 'skipped', 'message' => implode(' ', $order['problems'])]);
                continue;
            }

            try {
                $existing = SalesOrder::where('source_order_number', $key)->first();

                if ($isDryRun) {
                    $existing ? $updated++ : $created++;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $key, 'label' => $label, 'outcome' => $existing ? 'updated' : 'created', 'message' => 'Dry run — validated only.']);
                    continue;
                }

                DB::transaction(function () use ($order, $key, $existing, $label, $import) {
                    $customer = $this->resolveImportCustomer($order, $existing);

                    $subtotal = round($order['items_subtotal'] + $order['delivery_charge'], 2);
                    $vat = $order['vat'];
                    $grandTotal = $order['grand_total'];
                    $orderDate = $order['order_date'];

                    $orderPayload = [
                        'source_order_number' => $key,
                        'customer_id' => $customer->id,
                        'customer_phone' => $order['phone'],
                        'order_date' => $orderDate,
                        'order_month' => $orderDate->copy()->startOfMonth(),
                        'delivery_date' => $order['delivery_date'],
                        'emirate' => $order['emirate'],
                        'delivery_address' => $order['address'],
                        'fulfillment_type' => $order['fulfillment_type'],
                        'confirmation_status' => $order['cancelled'] ? 'cancelled' : 'confirmed',
                        'design_status' => 'designed',
                        'production_status' => 'completed',
                        'delivery_status' => $order['cancelled'] ? 'returned' : ($order['delivered'] ? 'delivered' : 'not_scheduled'),
                        'simple_status' => $order['delivered'] ? 'delivered' : 'pending',
                        'simple_confirmation' => 'confirmed',
                        'simple_design' => 'designed',
                        'payment_status' => $order['payment_status'],
                        'priority' => 'normal',
                        'is_very_urgent' => false,
                        'subtotal' => $subtotal,
                        'tax_total' => $vat,
                        'grand_total' => $grandTotal,
                        'notes' => $order['notes'],
                        'is_legacy_delivery_import' => true,
                    ];
                    $orderPayload = $this->onlyExistingColumns('sales_orders', $orderPayload);

                    if ($existing) {
                        $existing->update($orderPayload);
                        $salesOrder = $existing;
                        $salesOrder->items()->delete();
                    } else {
                        $orderPayload['order_number'] = 'LEG-'.$key;
                        $salesOrder = SalesOrder::create($orderPayload);
                    }

                    foreach ($order['items'] as $item) {
                        SalesOrderItem::create(['sales_order_id' => $salesOrder->id, 'description' => $item['description'], 'qty' => $item['qty'], 'unit_price' => round($item['line_total'] / $item['qty'], 4), 'line_total' => $item['line_total']]);
                    }
                    if ($order['delivery_charge'] > 0) {
                        SalesOrderItem::create(['sales_order_id' => $salesOrder->id, 'description' => 'Delivery charge', 'qty' => 1, 'unit_price' => $order['delivery_charge'], 'line_total' => $order['delivery_charge']]);
                    }

                    $invoice = $salesOrder->invoices()->first();
                    $paidAmount = min($grandTotal, $order['paid']);
                    $invoiceStatus = $order['cancelled'] ? 'cancelled' : ($paidAmount >= $grandTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'sent'));
                    $invoicePayload = [
                        'customer_id' => $customer->id, 'sales_order_id' => $salesOrder->id, 'invoice_date' => $orderDate,
                        'status' => $invoiceStatus, 'subtotal' => $subtotal, 'tax_total' => $vat, 'grand_total' => $grandTotal,
                        'amount_paid' => $paidAmount, 'outstanding_amount' => round($grandTotal - $paidAmount, 2),
                    ];
                    if ($invoice) {
                        $invoice->update($invoicePayload);
                        $invoice->items()->delete();
                    } else {
                        $invoicePayload['invoice_number'] = 'INV-'.str_replace('LEG-', '', $salesOrder->order_number ?? $key);
                        $invoice = Invoice::create($invoicePayload);
                    }
                    foreach ($order['items'] as $item) {
                        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $item['description'], 'qty' => $item['qty'], 'rate' => round($item['line_total'] / $item['qty'], 4), 'line_total' => $item['line_total']]);
                    }
                    if ($order['delivery_charge'] > 0) {
                        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Delivery charge', 'qty' => 1, 'rate' => $order['delivery_charge'], 'line_total' => $order['delivery_charge']]);
                    }

                    // Cancelled/returned orders are kept as history but never
                    // posted as revenue or cash.
                    if (!$order['cancelled'] && $grandTotal > 0) {
                        $this->postInvoiceIfNotAlready($invoice);
                        if ($paidAmount > 0) {
                            $this->postPaymentIfNotAlready($invoice, $paidAmount, (string) $orderDate->toDateString(), $order['payment_method']);
                        }
                    }

                    $message = $order['warnings'] ? implode(' ', $order['warnings']) : null;
                    DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $key, 'label' => $label, 'outcome' => $existing ? 'updated' : 'created', 'message' => $message]);
                });
                // Counted only after the transaction really committed.
                $existing ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $errors++;
                DataImportRow::create(['data_import_id' => $import->id, 'source_id' => $key, 'label' => $label, 'outcome' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $import->update(['status' => 'completed', 'created_count' => $created, 'updated_count' => $updated, 'skipped_count' => $skipped, 'conflict_count' => $conflicts, 'error_count' => $errors]);
        return $import;
    }

    /**
     * Same phone → same customer. No phone → reuse the customer already
     * on this order (re-import), or an imported customer with the exact
     * same name and no phone; never lump every phone-less customer into
     * one record.
     */
    private function resolveImportCustomer(array $order, ?SalesOrder $existing): Customer
    {
        $customer = null;
        if ($order['phone']) {
            $customer = Customer::where('phone', $order['phone'])->first();
        } elseif ($existing?->customer_id) {
            $customer = Customer::find($existing->customer_id);
        } else {
            $customer = Customer::whereNull('phone')->where('name', $order['customer_name'])->where('source', 'historical_import')->first();
        }
        if ($customer) {
            return $customer;
        }
        return Customer::create([
            'name' => $order['customer_name'],
            'phone' => $order['phone'],
            'emirate' => $order['emirate'],
            'delivery_address' => $order['address'],
            'customer_code' => $this->nextImportCustomerCode(),
            'status' => 'active',
            'source' => 'historical_import',
        ]);
    }

    private function nextImportCustomerCode(): string
    {
        $next = (int) Customer::withTrashed()->max('id') + 1;
        do {
            $code = 'CUS-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Customer::withTrashed()->where('customer_code', $code)->exists());
        return $code;
    }

    private array $columnCache = [];

    private function onlyExistingColumns(string $table, array $payload): array
    {
        $this->columnCache[$table] ??= array_flip(\Illuminate\Support\Facades\Schema::getColumnListing($table));
        return array_intersect_key($payload, $this->columnCache[$table]);
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
                    $this->postInvoiceIfNotAlready($invoice);
                    if ($paidAmount > 0) {
                        $this->postPaymentIfNotAlready($invoice, $paidAmount, (string) $orderDate->toDateString());
                    }
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
