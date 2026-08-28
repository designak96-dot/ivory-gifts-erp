<?php

namespace Tests\Feature;

use App\Models\{Customer, Invoice, InvoiceItem, Role, User};
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProofRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Proof Owner', 'email' => 'proof-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    private function invoiceWithBalance(): Invoice
    {
        $customer = Customer::create(['customer_code' => 'CUS-P1', 'name' => 'Test Customer', 'phone' => '+971501112222', 'status' => 'active']);
        $invoice = Invoice::create(['invoice_number' => 'INV-P-0001', 'customer_id' => $customer->id, 'invoice_date' => today(), 'status' => 'sent', 'subtotal' => 100, 'tax_total' => 5, 'grand_total' => 105, 'amount_paid' => 0, 'outstanding_amount' => 105]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Item', 'qty' => 1, 'rate' => 100, 'tax_amount' => 5, 'line_total' => 105]);
        return $invoice;
    }

    public function test_payment_is_rejected_without_proof(): void
    {
        $owner = $this->owner();
        $invoice = $this->invoiceWithBalance();

        $this->actingAs($owner)->post(route('invoices.payment', $invoice), [
            'amount' => 50, 'method' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertSessionHasErrors('proof');

        $this->assertEquals(0, $invoice->fresh()->amount_paid);
    }

    public function test_payment_succeeds_with_proof_and_is_privately_stored(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $invoice = $this->invoiceWithBalance();

        $this->actingAs($owner)->post(route('invoices.payment', $invoice), [
            'amount' => 50, 'method' => 'cash', 'payment_date' => today()->toDateString(),
            'proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertEquals(50, $invoice->fresh()->amount_paid);
        $payment = $invoice->fresh()->allocations->first()->payment;
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_proof_file_is_not_publicly_reachable_without_auth(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $invoice = $this->invoiceWithBalance();
        $this->actingAs($owner)->post(route('invoices.payment', $invoice), [
            'amount' => 50, 'method' => 'cash', 'payment_date' => today()->toDateString(),
            'proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ]);
        $payment = $invoice->fresh()->allocations->first()->payment;

        auth()->logout();
        $this->get(route('payments.proof', $payment))->assertRedirect(route('login'));
    }

    public function test_expense_is_rejected_without_proof(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => today()->toDateString(), 'category' => 'Rent', 'payment_method' => 'cash', 'amount_ex_tax' => 500,
        ])->assertSessionHasErrors('proof');
    }

    public function test_expense_succeeds_with_proof(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => today()->toDateString(), 'category' => 'Rent', 'payment_method' => 'cash', 'amount_ex_tax' => 500,
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $expense = \App\Models\Expense::latest()->first();
        $this->assertNotNull($expense->proof_path);
        Storage::disk('local')->assertExists($expense->proof_path);
    }
}
