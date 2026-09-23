<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Drill Owner', 'email' => 'drill-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        $this->actingAs($owner);
        return $owner;
    }

    private function invoice(string $number, float $total, float $paid, string $status): Invoice
    {
        $customer = Customer::firstOrCreate(['customer_code' => 'CUS-T1'], ['name' => 'Test Customer', 'status' => 'active']);
        return Invoice::create([
            'invoice_number' => $number, 'invoice_date' => '2026-07-01', 'customer_id' => $customer->id, 'status' => $status,
            'subtotal' => $total, 'tax_total' => 0, 'grand_total' => $total, 'amount_paid' => $paid, 'outstanding_amount' => $total - $paid,
        ]);
    }

    public function test_every_dashboard_box_links_to_its_list(): void
    {
        $this->owner();
        $response = $this->get(route('dashboard', ['month' => '2026-08']))->assertOk();

        $response->assertSee(route('invoices.index', ['outstanding' => 1]), false);
        $response->assertSee(route('orders.index', ['month' => '2026-08']), false);
        $response->assertSee(route('expenses.index', ['month' => '2026-08']), false);
        $response->assertSee(route('production.index', ['active' => 1]), false);
        $response->assertSee(route('products.index', ['low_stock' => 1]), false);
        $response->assertSee(route('deliveries.index', ['scope' => 'today']), false);
        $this->assertSame(11, substr_count($response->getContent(), 'class="stat stat-link"'));
    }

    public function test_outstanding_filter_shows_only_unpaid_invoices_with_matching_total(): void
    {
        $this->owner();
        $this->invoice('INV-PAID', 100, 100, 'paid');
        $this->invoice('INV-OPEN', 300, 0, 'sent');
        $this->invoice('INV-PART', 200, 50, 'partially_paid');
        $this->invoice('INV-CANC', 80, 0, 'cancelled');

        $this->get(route('invoices.index', ['outstanding' => 1]))
            ->assertOk()
            ->assertSee('INV-OPEN')->assertSee('INV-PART')
            ->assertDontSee('INV-PAID')->assertDontSee('INV-CANC')
            ->assertSee('AED 450.00 — show all', false);
    }

    public function test_paid_zero_is_red_and_outstanding_zero_is_green(): void
    {
        $this->owner();
        $this->invoice('INV-PAID', 100, 100, 'paid');
        $this->invoice('INV-OPEN', 300, 0, 'sent');

        $html = $this->get(route('invoices.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/INV-OPEN.*?data-label="Paid" class="amount kpi-bad">AED 0\.00.*?data-label="Outstanding" class="amount kpi-bad">AED 300\.00/s', $html);
        $this->assertMatchesRegularExpression('/INV-PAID.*?data-label="Paid" class="amount kpi-good">AED 100\.00.*?data-label="Outstanding" class="amount kpi-good">AED 0\.00/s', $html);
    }

    public function test_expenses_month_filter(): void
    {
        $this->owner();
        Expense::create(['expense_number' => 'EXP-AUG', 'expense_date' => '2026-08-10', 'category' => 'Rent', 'amount_ex_tax' => 500, 'total_amount' => 500]);
        Expense::create(['expense_number' => 'EXP-JUL', 'expense_date' => '2026-07-10', 'category' => 'Rent', 'amount_ex_tax' => 400, 'total_amount' => 400]);

        $this->get(route('expenses.index', ['month' => '2026-08']))
            ->assertOk()->assertSee('EXP-AUG')->assertDontSee('EXP-JUL')->assertSee('Expenses for August 2026');
    }

    public function test_low_stock_and_active_production_filters_load(): void
    {
        $this->owner();
        $this->get(route('products.index', ['low_stock' => 1]))->assertOk()->assertSee('reorder level');
        $this->get(route('production.index', ['active' => 1]))->assertOk()->assertSee('Active production jobs only');
    }
}
