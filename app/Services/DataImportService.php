<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DataImport;
use App\Models\DataImportRow;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
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
 * Conflict rule: if a match is found against a record that was NOT itself
 * created by a previous run of this importer (i.e. it has no source_id /
 * source_order_number yet — meaning a human created it directly in the
 * ERP), the import never silently overwrites it. That row is logged as a
 * conflict with both the existing and incoming values, for Owner review,
 * and nothing is written for that row.
 */
class DataImportService
{
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

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));
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
    // Orders
    // ---------------------------------------------------------------

    public function previewOrders(array $rows): array
    {
        $preview = [];
        foreach ($rows as $row) {
            $existing = !empty($row['source_order_number']) ? SalesOrder::where('source_order_number', $row['source_order_number'])->first() : null;
            $isConflict = $existing && !$existing->source_order_number;
            $preview[] = [
                'source_order_number' => $row['source_order_number'] ?? null,
                'customer' => $row['customer_name'] ?? '',
                'delivery_date' => $row['delivery_date'] ?? null,
                'action' => $isConflict ? 'conflict' : ($existing ? 'update' : 'create'),
            ];
        }
        return ['rows' => $preview, 'total' => count($preview)];
    }

    public function commitOrders(array $rows, int $userId, bool $isDryRun, ?SalesWorkflow $workflow = null): DataImport
    {
        $import = DataImport::create(['type' => 'orders', 'status' => 'pending', 'is_dry_run' => $isDryRun, 'total_rows' => count($rows), 'created_by' => $userId]);
        $created = $updated = $skipped = $conflicts = $errors = 0;

        foreach ($rows as $row) {
            $sourceNumber = trim((string) ($row['source_order_number'] ?? ''));
            $customerName = trim((string) ($row['customer_name'] ?? ''));

            if ($sourceNumber === '' || $customerName === '') {
                $skipped++;
                DataImportRow::create(['data_import_id' => $import->id, 'label' => $customerName ?: null, 'outcome' => 'skipped', 'message' => 'Missing source order number or customer name.']);
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

                DB::transaction(function () use ($row, $sourceNumber, $customerName, $existing, $workflow, &$created, &$updated, $import) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $this->safeNormalize($row['customer_phone'] ?? null)],
                        ['name' => $customerName, 'customer_code' => 'CUS-'.str_pad((string) (Customer::max('id') + 1), 5, '0', STR_PAD_LEFT), 'status' => 'active', 'source' => 'historical_import']
                    );

                    $orderPayload = [
                        'source_order_number' => $sourceNumber,
                        'customer_id' => $customer->id,
                        'order_date' => $row['order_date'] ?? now(),
                        'order_month' => $row['order_date'] ?? now(),
                        'delivery_date' => $row['delivery_date'] ?? null,
                        'emirate' => $row['emirate'] ?? null,
                        'confirmation_status' => $row['confirmation_status'] ?? 'confirmed',
                        'design_status' => $row['design_status'] ?? 'not_required',
                        'production_status' => $row['production_status'] ?? 'not_required',
                        'delivery_status' => !empty($row['delivery_date']) ? 'scheduled' : 'not_scheduled',
                        'payment_status' => $row['payment_status'] ?? 'unpaid',
                        'subtotal' => (float) ($row['total'] ?? 0),
                        'grand_total' => (float) ($row['total'] ?? 0),
                        'notes' => $row['notes'] ?? null,
                        'is_legacy_delivery_import' => true,
                    ];

                    if ($existing) {
                        $existing->update($orderPayload);
                        $order = $existing;
                        $updated++;
                    } else {
                        $orderPayload['order_number'] = 'LEG-'.$sourceNumber;
                        $order = SalesOrder::create($orderPayload);
                        SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => $row['description'] ?? 'Imported order', 'qty' => 1, 'unit_price' => (float) ($row['total'] ?? 0), 'line_total' => (float) ($row['total'] ?? 0)]);
                        $created++;
                    }

                    if ($order->delivery_date && $workflow) {
                        $workflow->createDelivery($order); // idempotent — checks for an existing delivery note first
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
}
