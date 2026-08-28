<?php

namespace Tests\Feature;

use App\Models\{Customer, Invoice, InvoiceItem, Payment, PaymentAllocation, Role, SalesOrder, User};
use App\Services\ProofUploadService;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderBalanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Balance Owner', 'email' => 'balance-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    private function orderWithPartialPayment(Customer $customer): SalesOrder
    {
        $order = SalesOrder::create(['order_number' => '01-00010126', 'manual_reference' => '01', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'delivery_date' => '2026-01-10', 'emirate' => 'Dubai', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'scheduled', 'payment_status' => 'unpaid', 'subtotal' => 1500, 'tax_total' => 0, 'grand_total' => 1500]);
        $invoice = Invoice::create(['invoice_number' => 'INV-01-00010126', 'customer_id' => $customer->id, 'sales_order_id' => $order->id, 'invoice_date' => today(), 'status' => 'partially_paid', 'subtotal' => 1500, 'tax_total' => 0, 'grand_total' => 1500, 'amount_paid' => 1000, 'outstanding_amount' => 500]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Item', 'qty' => 1, 'rate' => 1500, 'tax_amount' => 0, 'line_total' => 1500]);
        return $order;
    }

    public function test_paid_and_remaining_are_computed_correctly(): void
    {
        $customer = Customer::create(['customer_code' => 'CUS-B1', 'name' => 'Balance Test', 'status' => 'active']);
        $order = $this->orderWithPartialPayment($customer);

        $this->assertEquals(1000.0, $order->paid_amount);
        $this->assertEquals(500.0, $order->remaining_amount);
        $this->assertSame('partially_paid', $order->computed_payment_status);
    }

    public function test_unpaid_order_shows_correct_status(): void
    {
        $customer = Customer::create(['customer_code' => 'CUS-B2', 'name' => 'Unpaid Test', 'status' => 'active']);
        $order = SalesOrder::create(['order_number' => '02-00010126', 'manual_reference' => '02', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'delivery_date' => '2026-01-10', 'emirate' => 'Dubai', 'confirmation_status' => 'waiting', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'not_scheduled', 'payment_status' => 'unpaid', 'subtotal' => 800, 'tax_total' => 0, 'grand_total' => 800]);

        $this->assertEquals(0.0, $order->paid_amount);
        $this->assertEquals(800.0, $order->remaining_amount);
        $this->assertSame('unpaid', $order->computed_payment_status);
    }

    public function test_fully_paid_order_shows_correct_status(): void
    {
        $customer = Customer::create(['customer_code' => 'CUS-B3', 'name' => 'Paid Test', 'status' => 'active']);
        $order = SalesOrder::create(['order_number' => '03-00010126', 'manual_reference' => '03', 'order_month' => '2026-01-01', 'customer_id' => $customer->id, 'order_date' => '2026-01-01', 'delivery_date' => '2026-01-10', 'emirate' => 'Dubai', 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'delivered', 'payment_status' => 'paid', 'subtotal' => 300, 'tax_total' => 0, 'grand_total' => 300]);
        Invoice::create(['invoice_number' => 'INV-03-00010126', 'customer_id' => $customer->id, 'sales_order_id' => $order->id, 'invoice_date' => today(), 'status' => 'paid', 'subtotal' => 300, 'tax_total' => 0, 'grand_total' => 300, 'amount_paid' => 300, 'outstanding_amount' => 0]);

        $this->assertEquals(300.0, $order->paid_amount);
        $this->assertEquals(0.0, $order->remaining_amount);
        $this->assertSame('paid', $order->computed_payment_status);
    }

    public function test_customer_profile_shows_order_history_with_correct_balances(): void
    {
        $owner = $this->owner();
        $customer = Customer::create(['customer_code' => 'CUS-B4', 'name' => 'History Test', 'status' => 'active']);
        $this->orderWithPartialPayment($customer);

        $this->actingAs($owner)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Order history')
            ->assertSee('01-00010126')
            ->assertSee('1,000.00')
            ->assertSee('500.00');
    }

    public function test_orders_list_shows_paid_and_remaining_columns(): void
    {
        $owner = $this->owner();
        $customer = Customer::create(['customer_code' => 'CUS-B5', 'name' => 'List Test', 'status' => 'active']);
        $this->orderWithPartialPayment($customer);

        $this->actingAs($owner)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('Remaining');
    }
}
