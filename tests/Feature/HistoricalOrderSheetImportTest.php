<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DataImportService;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Mirrors the real monthly order sheet (july.csv): header-less customer
 * column, continuation item lines with a blank order number, "Delivery 16
 * July 🚨" dates, "Paidfully" / "Balance : 293 AED" payments, and two
 * money layouts (delivery+VAT amount vs. "5%" + total payable only).
 */
class HistoricalOrderSheetImportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Sheet Owner', 'email' => 'sheet-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        $this->actingAs($owner);
        return $owner;
    }

    private function sheet(): string
    {
        $rows = [
            ['source_order_number', '', 'customer_phone', 'order_date', 'delivery_date', 'item_description', 'emirate', 'item_qty', 'item_price', 'delivery_charge', 'vat_amount', 'total_payable', 'paid_amount', 'payment_status', 'confirmation_status', 'design_status', 'notes', ''],
            ['1', 'Shamma Al Zaabi', 'WhatsApp : 0563909259', '', 'Delivery 16 July', ' Ceramic Coffee Cups', 'AbuDhabi - MBZ - Villa 345', '12', '180.00', '40.00', '16.50', '', '', 'Paidfully', 'Delivered', 'Designed', '', ''],
            ['', '', 'Call : 0504911144', '', '', ' Tea Paper Cups ', '', '30', '75.00', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', ' Coffee Paper Cups', '', '30', '75.00', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['2', ' Sara', 'WhatsApp : 0565711190 /Call : 0567720070', '', '', 'Floor Stand 140 cm', 'Dubai - Khawaneej', '1', '200.00', '150.00', '10.00', '', '', 'Balance : 100 AED', 'Delivered', 'Designed', '', ''],
            ['', '', '', '', 'Delivery 18 August 🚨🚨', 'Acrylic Hangers', '', '70', '', '', '', '', '', '', '', '', '', ''],
            ['3', 'Umm Hamda', '506338322', '', 'Delivery 17 July 🚨🚨🚨', 'Table Stand 30x15 cm', ' Sharjah - Al Dhaid', '1', '150.00', '', '5%', '198.00', '', 'Paidfully', 'Delivered', 'Designed', '', ''],
            ['', '', '', '', '', 'Ceramic Coffee Cups', '', '0', '0.00', '', '5%', '', '', '', '', '', '', ''],
            ['4', '~', '501271377', '', 'Pickup 3 July', 'Tissue Boxes', 'Personal Pickup', '3', '45.00', '', '', '', '', 'Pending Payment', 'Delivered', 'Designed', '', ''],
        ];
        $path = tempnam(sys_get_temp_dir(), 'sheet').'.csv';
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return $path;
    }

    public function test_preview_reads_names_items_and_dates_from_the_real_sheet_layout(): void
    {
        $this->owner();
        $service = app(DataImportService::class);
        $preview = $service->previewOrders($service->parseFile($this->sheet(), 'csv'), '2026-07');

        $this->assertSame(4, $preview['total'], 'Continuation lines must not become extra orders.');
        $this->assertSame(['create' => 4], $preview['counts']);
        $this->assertSame('Shamma Al Zaabi', $preview['rows'][0]['customer']);
        $this->assertSame(3, $preview['rows'][0]['line_count']);
        $this->assertSame('16 Jul 2026', $preview['rows'][0]['delivery_date']);
        $this->assertSame('18 Aug 2026', $preview['rows'][1]['delivery_date'], 'Delivery date on a later line of the order is used.');
        $this->assertSame('Customer +971501271377', $preview['rows'][3]['customer']);
        $this->assertSame('pickup', $preview['rows'][3]['fulfillment_type']);
    }

    public function test_commit_imports_every_order_with_correct_money_and_status(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $rows = $service->parseFile($this->sheet(), 'csv');

        $import = $service->commitOrders($rows, $owner->id, isDryRun: false, sheetMonth: '2026-07');

        $this->assertSame(4, $import->created_count);
        $this->assertSame(0, $import->skipped_count);
        $this->assertSame(0, $import->error_count);

        // Layout A: items 330 + delivery 40 + VAT 16.50
        $one = SalesOrder::where('source_order_number', '202607-1')->firstOrFail();
        $this->assertSame('LEG-202607-1', $one->order_number);
        $this->assertSame('2026-07-01', $one->order_date->toDateString());
        $this->assertSame('2026-07-16', $one->delivery_date->toDateString());
        $this->assertSame('Abu Dhabi', $one->emirate);
        $this->assertEquals(386.50, (float) $one->grand_total);
        $this->assertSame('paid', $one->payment_status);
        $this->assertSame('delivered', $one->delivery_status);
        $this->assertSame(4, $one->items()->count(), '3 items + delivery charge line');
        $this->assertSame('+971563909259', $one->customer->phone);

        // Balance : 100 AED → partially paid
        $two = SalesOrder::where('source_order_number', '202607-2')->firstOrFail();
        $invoice = Invoice::where('sales_order_id', $two->id)->firstOrFail();
        $this->assertEquals(360.00, (float) $invoice->grand_total);
        $this->assertEquals(260.00, (float) $invoice->amount_paid);
        $this->assertSame('partially_paid', $two->payment_status);
        $this->assertStringContainsString('+971567720070', (string) $two->notes);

        // Layout B: 150 items, 5%, total 198 → delivery 40.50, VAT 7.50; qty-0 line left out
        $three = SalesOrder::where('source_order_number', '202607-3')->firstOrFail();
        $this->assertEquals(198.00, (float) $three->grand_total);
        $this->assertEquals(7.50, (float) $three->tax_total);
        $this->assertSame(2, $three->items()->count());

        $four = SalesOrder::where('source_order_number', '202607-4')->firstOrFail();
        $this->assertSame('unpaid', $four->payment_status);
        $this->assertSame('pickup', $four->fulfillment_type);
    }

    public function test_reimport_updates_and_next_month_does_not_overwrite(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $rows = $service->parseFile($this->sheet(), 'csv');

        $service->commitOrders($rows, $owner->id, isDryRun: false, sheetMonth: '2026-07');
        $again = $service->commitOrders($rows, $owner->id, isDryRun: false, sheetMonth: '2026-07');
        $this->assertSame(4, $again->updated_count);
        $this->assertSame(4, SalesOrder::count());

        $august = $service->commitOrders($rows, $owner->id, isDryRun: false, sheetMonth: '2026-08');
        $this->assertSame(4, $august->created_count, 'August #1 is a different order from July #1.');
        $this->assertSame(8, SalesOrder::count());
        $this->assertSame(4, Customer::count(), 'Same phone numbers reuse the same customers.');
    }

    public function test_upload_requires_sheet_month_and_preview_page_shows_customers(): void
    {
        $this->owner();
        $file = fn () => new UploadedFile($this->sheet(), 'july.csv', 'text/csv', null, true);

        $this->post(route('imports.preview'), ['type' => 'orders', 'file' => $file()])
            ->assertSessionHasErrors('sheet_month');

        $this->post(route('imports.preview'), ['type' => 'orders', 'sheet_month' => '2026-07', 'file' => $file()])
            ->assertOk()
            ->assertSee('Shamma Al Zaabi')
            ->assertSee('July 2026')
            ->assertSee('4 to create');
    }
}
