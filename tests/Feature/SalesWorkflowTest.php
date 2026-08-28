<?php
namespace Tests\Feature;
use App\Models\{Customer,Invoice,JournalEntry,Product,Quotation,Role,SalesOrder,TaxRate,User};
use App\Services\SalesWorkflow;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    protected function setUp():void{parent::setUp();$this->seed(SystemDataSeeder::class);$this->owner=User::factory()->create(['email'=>'owner@test.local','is_active'=>true]);$this->owner->roles()->attach(Role::where('name','owner')->first());$this->actingAs($this->owner);}
    public function test_quotation_to_paid_invoice_posts_balanced_journals():void
    {
        $customer=Customer::create(['customer_code'=>'CUS-TEST','name'=>'Test Customer','phone'=>'+971501234567','whatsapp'=>'+971501234567','payment_terms_days'=>7,'status'=>'active']);
        $product=Product::create(['sku'=>'GIFT-1','name_en'=>'Personalised Gift','sale_price'=>100,'cost_price'=>40,'tax_rate_id'=>TaxRate::first()->id,'unit'=>'piece','is_active'=>true]);
        $quotation=Quotation::create(['quotation_number'=>'QT-TEST','customer_id'=>$customer->id,'quotation_date'=>today(),'valid_until'=>today()->addDays(14),'status'=>'approved','subtotal'=>100,'discount_total'=>10,'tax_total'=>4.5,'grand_total'=>94.5]);
        $quotation->items()->create(['product_id'=>$product->id,'description'=>$product->name_en,'qty'=>1,'unit_price'=>100,'discount'=>10,'tax_rate'=>5,'tax_amount'=>4.5,'line_total'=>94.5]);
        $workflow=app(SalesWorkflow::class);
        $order=$workflow->quotationToOrder($quotation->load('items','customer'));
        $this->assertEquals(90.0,(float)$order->subtotal);$this->assertNotNull($order->productionJob);
        $invoice=$workflow->orderToInvoice($order->load('items','customer'));
        $this->assertEquals(94.5,(float)$invoice->outstanding_amount);
        $entry=JournalEntry::where('reference_type',Invoice::class)->where('reference_id',$invoice->id)->firstOrFail();
        $this->assertEquals((float)$entry->lines()->sum('debit'),(float)$entry->lines()->sum('credit'));
        $workflow->recordPayment($invoice->fresh(),['amount'=>40,'method'=>'bank_transfer','payment_date'=>today()->toDateString()]);
        $this->assertEquals(54.5,(float)$invoice->fresh()->outstanding_amount);
        $workflow->recordPayment($invoice->fresh(),['amount'=>54.5,'method'=>'cash','payment_date'=>today()->toDateString()]);
        $this->assertSame('paid',$invoice->fresh()->status);$this->assertSame('confirmed',$order->fresh()->confirmation_status);
        $delivery=$workflow->createDelivery($order->fresh());$this->assertSame($order->id,$delivery->sales_order_id);
        $this->get(route('quotations.show',$quotation))->assertOk();$this->get(route('orders.show',$order))->assertOk();$this->get(route('invoices.show',$invoice))->assertOk();$this->get(route('deliveries.show',$delivery))->assertOk();
        foreach(JournalEntry::with('lines')->get() as $journal)$this->assertEquals(round((float)$journal->lines->sum('debit'),2),round((float)$journal->lines->sum('credit'),2));
    }
    public function test_read_only_user_cannot_create_customer():void
    {
        $user=User::factory()->create(['is_active'=>true]);$user->roles()->attach(Role::where('name','read_only')->first());
        $this->actingAs($user)->get(route('customers.create'))->assertForbidden();
    }
}
