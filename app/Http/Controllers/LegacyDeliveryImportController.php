<?php

namespace App\Http\Controllers;

use App\Models\{Customer, DeliveryNote, SalesOrder, SyncLog, User};
use App\Services\{DeliverySchedulingService, NumberingService, PhoneNormalizer};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyDeliveryImportController extends Controller
{
    public function __invoke(
        Request $request,
        NumberingService $numbers,
        PhoneNormalizer $phones,
        DeliverySchedulingService $scheduler,
    ) {
        $data = $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);
        $handle = fopen($data['file']->getRealPath(), 'r');
        $rawHeaders = fgetcsv($handle) ?: [];
        $headers = array_map([$this, 'headerKey'], $rawHeaders);
        $created = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            try {
                $values = array_pad($row, count($headers), null);
                $record = array_combine($headers, array_slice($values, 0, count($headers)));
                $record = $this->normaliseRecord($record ?: []);
                if (empty($record['order_number'])) {
                    throw new \RuntimeException('Order Number is required.');
                }
                if (SalesOrder::where('order_number', $record['order_number'])->exists()) {
                    $skipped++;
                    continue;
                }

                DB::transaction(function () use ($record, $numbers, $phones, $scheduler, &$created) {
                    $phone = $phones->normalize($record['phone'] ?: null);
                    $customer = ($phone ? Customer::where('phone', $phone)->first() : null)
                        ?? Customer::where('name', $record['customer_name'])->first();
                    if (!$customer) {
                        $customer = Customer::create([
                            'customer_code' => $numbers->next('customer'),
                            'name' => $record['customer_name'] ?: 'Legacy customer',
                            'phone' => $phone,
                            'whatsapp' => $phone,
                            'emirate' => $record['emirate'] ?: null,
                            'delivery_address' => $record['area'] ?: null,
                            'status' => 'active',
                            'source' => 'legacy_delivery_import',
                        ]);
                    }

                    $date = Carbon::parse($record['delivery_date'] ?: today());
                    $driver = $record['driver'] ? User::where('name', $record['driver'])->first() : null;
                    $deliveryStatus = $this->deliveryStatus($record['status']);
                    $priority = $this->priority($record['priority']);
                    $completed = $deliveryStatus === 'delivered';
                    $returned = $deliveryStatus === 'returned';
                    $order = SalesOrder::create([
                        'order_number' => $record['order_number'],
                        'order_month' => $record['order_month']
                            ? Carbon::createFromFormat('Y-m', $record['order_month'])->startOfMonth()
                            : $date->copy()->startOfMonth(),
                        'customer_id' => $customer->id,
                        'order_date' => $date,
                        'delivery_date' => $date,
                        'emirate' => $record['emirate'] ?: $customer->emirate,
                        'delivery_address' => $record['area'] ?: $customer->delivery_address,
                        'driver_id' => $driver?->id,
                        'confirmation_status' => $completed ? 'confirmed' : ($returned ? 'cancelled' : 'waiting'),
                        'design_status' => $completed ? 'designed' : 'need_design',
                        'production_status' => ($completed || $returned) ? 'completed' : 'waiting',
                        'delivery_status' => $deliveryStatus,
                        'priority' => $priority === 'very_urgent' ? 'urgent' : $priority,
                        'is_very_urgent' => $priority === 'very_urgent' && !in_array($deliveryStatus, ['delivered', 'returned'], true),
                        'is_legacy_delivery_import' => true,
                        'subtotal' => 0,
                        'tax_total' => 0,
                        'grand_total' => 0,
                        'notes' => trim('Imported from Ivory Delivery Management. '.$record['notes']),
                    ]);

                    $packageSize = 'standard';
                    $delivery = DeliveryNote::create([
                        'delivery_note_number' => $numbers->next('delivery_note'),
                        'sales_order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'driver_id' => $driver?->id,
                        'delivery_date' => $date,
                        'status' => $this->noteStatus($record['status']),
                        'package_size' => $packageSize,
                        'delivery_charge' => $scheduler->charge($record['emirate'], $packageSize),
                        'delivered_at' => $deliveryStatus === 'delivered' ? $date->copy()->endOfDay() : null,
                        'delivery_notes' => trim('Legacy source: '.($record['source'] ?: 'manual').'. '.$record['notes']),
                    ]);

                    SyncLog::create([
                        'source' => 'legacy_delivery',
                        'direction' => 'in',
                        'external_reference' => $record['order_number'],
                        'payload_hash' => hash('sha256', json_encode($record)),
                        'status' => 'success',
                        'payload' => $record + ['delivery_note_id' => $delivery->id],
                        'synced_at' => now(),
                    ]);
                    $created++;
                });
            } catch (\Throwable $exception) {
                $errors[] = "Row {$rowNumber}: {$exception->getMessage()}";
                if (count($errors) >= 30) {
                    break;
                }
            }
        }
        fclose($handle);

        return back()
            ->with('success', "Plugin CSV import: {$created} created, {$skipped} existing orders safely skipped.")
            ->with('import_errors', $errors);
    }

    private function headerKey(string $header): string
    {
        $key = strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        return trim($key, '_');
    }

    private function normaliseRecord(array $row): array
    {
        return [
            'order_number' => trim((string) ($row['order_number'] ?? '')),
            'order_month' => trim((string) ($row['order_month'] ?? '')),
            'customer_name' => trim((string) ($row['customer_name'] ?? $row['customer'] ?? '')),
            'phone' => trim((string) ($row['customer_phone'] ?? $row['phone'] ?? '')),
            'delivery_date' => trim((string) ($row['delivery_date'] ?? '')),
            'emirate' => trim((string) ($row['emirate'] ?? '')),
            'area' => trim((string) ($row['area'] ?? '')),
            'driver' => trim((string) ($row['driver_name'] ?? $row['driver'] ?? '')),
            'status' => $this->token($row['status'] ?? 'pending'),
            'priority' => $this->token($row['priority'] ?? 'normal'),
            'source' => trim((string) ($row['source'] ?? 'manual')),
            'notes' => trim((string) ($row['notes'] ?? '')),
        ];
    }

    private function token(mixed $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value)), '_');
    }

    private function deliveryStatus(string $status): string
    {
        return match ($status) {
            'ready_to_deliver', 'ready_for_delivery', 'pending', 'scheduled' => 'scheduled',
            'out_for_delivery', 'ready_out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'failed', 'partial' => 'failed',
            'returned', 'cancelled', 'canceled' => 'returned',
            default => 'scheduled',
        };
    }

    private function noteStatus(string $status): string
    {
        return match ($this->deliveryStatus($status)) {
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'returned' => 'returned',
            default => 'pending',
        };
    }

    private function priority(string $priority): string
    {
        return match ($priority) {
            'very_urgent', 'veryurgent' => 'very_urgent',
            'urgent', 'high' => 'urgent',
            default => 'normal',
        };
    }
}
