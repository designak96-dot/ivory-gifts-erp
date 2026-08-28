<?php

namespace Tests\Feature;

use App\Models\{Customer, Invoice, InvoiceItem, Product, Quotation, QuotationItem, Role, SalesOrder, SalesOrderItem, DeliveryNote, Setting, User};
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Doc Owner', 'email' => 'doc-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    private function setBranding(): void
    {
        Setting::updateOrCreate(['key' => 'company_legal_name'], ['value' => 'Ivory Gifts Trading LLC', 'group' => 'branding']);
        Setting::updateOrCreate(['key' => 'company_trn'], ['value' => '100123456700003', 'group' => 'general']);
        Setting::updateOrCreate(['key' => 'quotation_terms'], ['value' => 'Fifty percent deposit is required upfront.', 'group' => 'branding']);
        Setting::updateOrCreate(['key' => 'invoice_terms'], ['value' => 'Payment due within seven days of invoice date.', 'group' => 'branding']);
        Setting::updateOrCreate(['key' => 'document_footer'], ['value' => 'Thank you for choosing Ivory Gifts.', 'group' => 'branding']);
    }

    private function customer(): Customer
    {
        return Customer::create(['customer_code' => 'CUS-00001', 'name' => 'Fatima Al Suwaidi', 'phone' => '+971501234567', 'billing_address' => 'Abu Dhabi', 'status' => 'active']);
    }

    public function test_quotation_print_shows_company_trn_and_terms(): void
    {
        $owner = $this->owner();
        $this->setBranding();
        $customer = $this->customer();

        $quotation = Quotation::create(['quotation_number' => 'QT-2026-0001', 'customer_id' => $customer->id, 'quotation_date' => today(), 'status' => 'draft', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Gift hamper', 'qty' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 5, 'tax_amount' => 5, 'line_total' => 105]);

        $response = $this->actingAs($owner)->get(route('quotations.show', $quotation));

        $response->assertOk()
            ->assertSee('Ivory Gifts Trading LLC')
            ->assertSee('100123456700003')
            ->assertSee('Fifty percent deposit is required upfront.')
            ->assertSee('Thank you for choosing Ivory Gifts.');
    }

    public function test_invoice_print_shows_bank_details_and_terms(): void
    {
        $owner = $this->owner();
        $this->setBranding();
        Setting::updateOrCreate(['key' => 'company_bank_details'], ['value' => 'Emirates NBD, IBAN AE000000000000000000000', 'group' => 'branding']);
        $customer = $this->customer();

        $invoice = Invoice::create(['invoice_number' => 'INV-2026-0001', 'customer_id' => $customer->id, 'invoice_date' => today(), 'status' => 'sent', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105, 'amount_paid' => 0, 'outstanding_amount' => 105]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Gift hamper', 'qty' => 1, 'rate' => 100, 'tax_amount' => 5, 'line_total' => 105]);

        $this->actingAs($owner)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Emirates NBD, IBAN AE000000000000000000000')
            ->assertSee('Payment due within seven days of invoice date.');
    }

    public function test_delivery_note_hides_prices_by_default_when_configured(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Setting::updateOrCreate(['key' => 'delivery_note_hide_prices'], ['value' => '1', 'group' => 'branding']);
        $customer = $this->customer();
        $order = SalesOrder::create(['order_number' => 'SO-2026-0001', 'order_month' => now()->format('Y-m'), 'customer_id' => $customer->id, 'order_date' => today(), 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105]);
        SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => 'Gift hamper', 'qty' => 1, 'unit_price' => 100, 'tax_amount' => 5, 'line_total' => 105]);
        $delivery = DeliveryNote::create(['delivery_note_number' => 'DN-2026-0001', 'sales_order_id' => $order->id, 'customer_id' => $customer->id, 'delivery_date' => today(), 'status' => 'pending', 'package_size' => 'standard', 'delivery_charge' => 20]);

        $response = $this->actingAs($owner)->get(route('deliveries.show', $delivery));

        $response->assertOk()->assertDontSee('AED 20.00')->assertSee('Show prices');
    }

    public function test_delivery_note_shows_prices_when_toggled_on(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Setting::updateOrCreate(['key' => 'delivery_note_hide_prices'], ['value' => '1', 'group' => 'branding']);
        $customer = $this->customer();
        $order = SalesOrder::create(['order_number' => 'SO-2026-0002', 'order_month' => now()->format('Y-m'), 'customer_id' => $customer->id, 'order_date' => today(), 'confirmation_status' => 'confirmed', 'design_status' => 'not_required', 'production_status' => 'not_required', 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105]);
        SalesOrderItem::create(['sales_order_id' => $order->id, 'description' => 'Gift hamper', 'qty' => 1, 'unit_price' => 100, 'tax_amount' => 5, 'line_total' => 105]);
        $delivery = DeliveryNote::create(['delivery_note_number' => 'DN-2026-0002', 'sales_order_id' => $order->id, 'customer_id' => $customer->id, 'delivery_date' => today(), 'status' => 'pending', 'package_size' => 'standard', 'delivery_charge' => 20]);

        $response = $this->actingAs($owner)->get(route('deliveries.show', ['delivery' => $delivery->id, 'hide_prices' => 0]));

        $response->assertOk()->assertSee('AED 20.00')->assertSee('Hide prices');
    }

    public function test_logo_appears_on_all_three_document_types_when_configured(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Storage::disk('public')->put('branding/logo_test.png', 'fake-png-bytes');
        Setting::updateOrCreate(['key' => 'logo_path'], ['value' => 'branding/logo_test.png', 'group' => 'branding']);
        $customer = $this->customer();

        $quotation = Quotation::create(['quotation_number' => 'QT-2026-0002', 'customer_id' => $customer->id, 'quotation_date' => today(), 'status' => 'draft', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Item', 'qty' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 5, 'tax_amount' => 5, 'line_total' => 105]);

        $this->actingAs($owner)->get(route('quotations.show', $quotation))->assertOk()->assertSee('doc-logo', false);
    }
}
