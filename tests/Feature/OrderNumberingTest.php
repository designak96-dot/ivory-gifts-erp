<?php

namespace Tests\Feature;

use App\Models\{Customer, Product, Role, SalesOrder, TaxRate, User};
use App\Services\{NumberingService, SalesWorkflow};
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Numbering Owner', 'email' => 'numbering-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    public function test_generates_manual_xxxx_mmyy_format(): void
    {
        $this->owner();
        $numbers = app(NumberingService::class);
        $number = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 15));
        $this->assertSame('01-00010126', $number);
    }

    public function test_sequence_increments_within_the_same_month_regardless_of_manual_value(): void
    {
        $this->owner();
        $numbers = app(NumberingService::class);
        $first = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 5));
        $second = $numbers->nextSalesOrderNumber('02', \Carbon\Carbon::create(2026, 1, 20));
        $this->assertSame('01-00010126', $first);
        $this->assertSame('02-00020126', $second);
    }

    public function test_sequence_resets_for_a_new_month(): void
    {
        $this->owner();
        $numbers = app(NumberingService::class);
        $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 5));
        $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 6));
        $february = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 2, 1));
        $this->assertSame('01-00010226', $february);
    }

    public function test_january_and_february_sequences_are_independent_even_out_of_order(): void
    {
        $this->owner();
        $numbers = app(NumberingService::class);
        $feb1 = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 2, 1));   // Feb #1
        $feb2 = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 2, 2));   // Feb #2
        $lateJan = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 28)); // Jan entered AFTER Feb
        $janAgain = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 1, 29)); // continues Jan's own count
        $feb3 = $numbers->nextSalesOrderNumber('01', \Carbon\Carbon::create(2026, 2, 3));   // Feb continues unaffected

        $this->assertSame('01-00010226', $feb1);
        $this->assertSame('01-00020226', $feb2);
        $this->assertSame('01-00010126', $lateJan, 'A January order entered after February must start/continue Januarys own sequence, not collide with February.');
        $this->assertSame('01-00020126', $janAgain);
        $this->assertSame('01-00030226', $feb3, 'February must not have been reset or disturbed by the interleaved January entries.');
    }

    public function test_two_concurrent_requests_never_receive_the_same_order_number(): void
    {
        $this->owner();
        $numbers = app(NumberingService::class);
        $date = \Carbon\Carbon::create(2026, 3, 1);
        $a = $numbers->nextSalesOrderNumber('01', $date);
        $b = $numbers->nextSalesOrderNumber('01', $date);
        $this->assertNotSame($a, $b);
    }

    public function test_order_number_is_rejected_as_duplicate_at_the_database_level(): void
    {
        $this->owner();
        $customer = Customer::create(['customer_code' => 'CUS-N1', 'name' => 'Test', 'status' => 'active']);
        SalesOrder::create(['order_number' => '01-00010126', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 0, 'tax_total' => 0, 'grand_total' => 0]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SalesOrder::create(['order_number' => '01-00010126', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 0, 'tax_total' => 0, 'grand_total' => 0]);
    }

    public function test_invoice_number_matches_sales_order_number_with_inv_prefix(): void
    {
        $owner = $this->owner();
        $customer = Customer::create(['customer_code' => 'CUS-N2', 'name' => 'Test Two', 'status' => 'active']);
        $product = Product::create(['sku' => 'TESTSK', 'name_en' => 'Test Product', 'sale_price' => 100, 'cost_price' => 50, 'unit' => 'piece', 'is_active' => true]);
        $order = SalesOrder::create(['order_number' => '05-00010126', 'manual_reference' => '05', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'delivery_date' => '2026-01-10', 'emirate' => 'Dubai', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105]);
        $order->items()->create(['product_id' => $product->id, 'description' => 'Test Product', 'qty' => 1, 'unit_price' => 100, 'tax_amount' => 5, 'line_total' => 105]);

        $workflow = app(SalesWorkflow::class);
        $invoice = $workflow->orderToInvoice($order->fresh(['items', 'customer']));

        $this->assertSame('INV-05-00010126', $invoice->invoice_number);
        $this->assertSame($order->id, $invoice->sales_order_id);
    }

    public function test_a_second_orders_invoice_never_reuses_the_first_orders_number(): void
    {
        $this->owner();
        $customer = Customer::create(['customer_code' => 'CUS-N3', 'name' => 'Test Three', 'status' => 'active']);
        $orderA = SalesOrder::create(['order_number' => '01-00010126', 'manual_reference' => '01', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'delivery_date' => '2026-01-10', 'emirate' => 'Dubai', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 100, 'tax_total' => 0, 'grand_total' => 100]);
        $orderA->items()->create(['description' => 'Item', 'qty' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $orderB = SalesOrder::create(['order_number' => '02-00020126', 'manual_reference' => '02', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-02', 'delivery_date' => '2026-01-11', 'emirate' => 'Dubai', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 200, 'tax_total' => 0, 'grand_total' => 200]);
        $orderB->items()->create(['description' => 'Item', 'qty' => 1, 'unit_price' => 200, 'line_total' => 200]);

        $workflow = app(SalesWorkflow::class);
        $invoiceA = $workflow->orderToInvoice($orderA->fresh(['items', 'customer']));
        $invoiceB = $workflow->orderToInvoice($orderB->fresh(['items', 'customer']));

        $this->assertSame('INV-01-00010126', $invoiceA->invoice_number);
        $this->assertSame('INV-02-00020126', $invoiceB->invoice_number);
        $this->assertNotSame($invoiceA->invoice_number, $invoiceB->invoice_number);
    }
}
