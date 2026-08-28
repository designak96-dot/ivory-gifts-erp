<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DataImportService;
use App\Services\SalesWorkflow;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Import Owner', 'email' => 'data-import-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    private function csvPath(array $rows, array $headers): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return $path;
    }

    public function test_customer_import_creates_new_customers_from_csv(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $path = $this->csvPath([['src-1', 'Fatima Al Suwaidi', '0501234567', 'fatima@example.test', 'Dubai']], ['source_id', 'name', 'phone', 'email', 'emirate']);

        $rows = $service->parseFile($path, 'csv');
        $import = $service->commitCustomers($rows, $owner->id, isDryRun: false);

        $this->assertSame(1, $import->created_count);
        $this->assertSame(0, $import->conflict_count);
        $customer = Customer::where('source_id', 'src-1')->first();
        $this->assertNotNull($customer);
        $this->assertSame('+971501234567', $customer->phone);
    }

    public function test_customer_import_matches_existing_by_phone_and_updates(): void
    {
        $owner = $this->owner();
        Customer::create(['customer_code' => 'CUS-00001', 'name' => 'Old Name', 'phone' => '+971501234567', 'source_id' => 'legacy-1', 'status' => 'active']);
        $service = app(DataImportService::class);
        $path = $this->csvPath([['legacy-1', 'Fatima Al Suwaidi (updated)', '0501234567', '', 'Dubai']], ['source_id', 'name', 'phone', 'email', 'emirate']);

        $rows = $service->parseFile($path, 'csv');
        $import = $service->commitCustomers($rows, $owner->id, isDryRun: false);

        $this->assertSame(0, $import->created_count);
        $this->assertSame(1, $import->updated_count);
        $this->assertSame(1, Customer::count());
        $this->assertSame('Fatima Al Suwaidi (updated)', Customer::first()->name);
    }

    public function test_customer_import_never_overwrites_a_manually_created_customer_matched_by_phone(): void
    {
        $owner = $this->owner();
        // Manually created — no source_id — same phone as an incoming import row.
        Customer::create(['customer_code' => 'CUS-00001', 'name' => 'Manually Entered Customer', 'phone' => '+971501234567', 'status' => 'active']);
        $service = app(DataImportService::class);
        $path = $this->csvPath([['src-9', 'Conflicting Import Row', '0501234567', '', '']], ['source_id', 'name', 'phone', 'email', 'emirate']);

        $rows = $service->parseFile($path, 'csv');
        $import = $service->commitCustomers($rows, $owner->id, isDryRun: false);

        $this->assertSame(1, $import->conflict_count);
        $this->assertSame(0, $import->created_count);
        $this->assertSame(0, $import->updated_count);
        $this->assertSame('Manually Entered Customer', Customer::first()->name, 'A manually entered record must never be silently overwritten by an import.');
    }

    public function test_customer_import_is_idempotent_on_re_run(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $path = $this->csvPath([['src-1', 'Fatima', '0501234567', '', 'Dubai']], ['source_id', 'name', 'phone', 'email', 'emirate']);
        $rows = $service->parseFile($path, 'csv');

        $service->commitCustomers($rows, $owner->id, isDryRun: false);
        $this->assertSame(1, Customer::count());

        $second = $service->commitCustomers($rows, $owner->id, isDryRun: false);
        $this->assertSame(1, Customer::count(), 'Re-running the same import must upsert, never duplicate.');
        $this->assertSame(1, $second->updated_count);
    }

    public function test_order_import_creates_order_and_links_exactly_one_delivery_note(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $workflow = app(SalesWorkflow::class);
        $path = $this->csvPath([
            ['ORD-1001', 'Zayed Corp', '0507654321', now()->addDays(3)->toDateString(), now()->toDateString(), 'Dubai', '500'],
        ], ['source_order_number', 'customer_name', 'customer_phone', 'delivery_date', 'order_date', 'emirate', 'total']);
        $rows = $service->parseFile($path, 'csv');

        $import = $service->commitOrders($rows, $owner->id, isDryRun: false, workflow: $workflow);

        $this->assertSame(1, $import->created_count);
        $order = SalesOrder::where('source_order_number', 'ORD-1001')->first();
        $this->assertNotNull($order);
        $this->assertTrue((bool) $order->is_legacy_delivery_import);
        $this->assertSame(1, DeliveryNote::where('sales_order_id', $order->id)->count());
    }

    public function test_re_importing_the_same_order_does_not_duplicate_the_delivery_note(): void
    {
        $owner = $this->owner();
        $service = app(DataImportService::class);
        $workflow = app(SalesWorkflow::class);
        $path = $this->csvPath([
            ['ORD-2002', 'Al Ain Nursery', '0509998888', now()->addDays(2)->toDateString(), now()->toDateString(), 'Al Ain', '300'],
        ], ['source_order_number', 'customer_name', 'customer_phone', 'delivery_date', 'order_date', 'emirate', 'total']);
        $rows = $service->parseFile($path, 'csv');

        $service->commitOrders($rows, $owner->id, isDryRun: false, workflow: $workflow);
        $service->commitOrders($rows, $owner->id, isDryRun: false, workflow: $workflow);

        $order = SalesOrder::where('source_order_number', 'ORD-2002')->first();
        $this->assertSame(1, SalesOrder::where('source_order_number', 'ORD-2002')->count());
        $this->assertSame(1, DeliveryNote::where('sales_order_id', $order->id)->count());
    }

    public function test_xlsx_is_explicitly_rejected_with_a_clear_message(): void
    {
        $service = app(DataImportService::class);
        $this->expectExceptionMessage('XLSX is not supported');
        $service->parseFile('/tmp/whatever.xlsx', 'xlsx');
    }

    public function test_only_owner_can_reach_the_import_wizard(): void
    {
        $this->seed(SystemDataSeeder::class);
        $sales = User::create(['name' => 'Sales', 'email' => 'sales-import@example.test', 'password' => 'test-password', 'is_active' => true]);
        $sales->roles()->attach(Role::where('name', 'sales')->firstOrFail());

        $this->actingAs($sales)->get(route('imports.create'))->assertForbidden();
    }
}
