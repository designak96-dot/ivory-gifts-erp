<?php

namespace App\Services;

use App\Models\{DeliveryNote,Invoice,Payment,ProductionJob,Quotation,SalesOrder};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesWorkflow
{
    public function __construct(
        private NumberingService $numbers,
        private AccountingService $accounting,
        private DeliverySchedulingService $deliveryScheduler,
        private ProofUploadService $proofs,
    ) {}

    public function quotationToOrder(
        Quotation $quotation,
        string $manualReference,
        \Carbon\Carbon $orderDate,
        \Carbon\Carbon $deliveryDate,
        string $priority = 'normal',
    ): SalesOrder
    {
        return DB::transaction(function() use($quotation,$manualReference,$orderDate,$deliveryDate,$priority){
            if($quotation->status==='converted') throw new RuntimeException('Quotation was already converted.');
            $orderNumber = $this->numbers->formatSalesOrderNumber($manualReference, $orderDate);
            $order=SalesOrder::create(['order_number'=>$orderNumber,'manual_reference'=>strtoupper($manualReference),'order_month'=>$orderDate->copy()->startOfMonth(),'customer_id'=>$quotation->customer_id,'customer_phone'=>$quotation->customer->phone,'quotation_id'=>$quotation->id,'order_date'=>$orderDate,'delivery_date'=>$deliveryDate,'priority'=>$priority,'emirate'=>$quotation->customer->emirate,'delivery_address'=>$quotation->customer->delivery_address,'confirmation_status'=>'confirmed','design_status'=>'need_design','production_status'=>'waiting','delivery_status'=>'not_scheduled','payment_status'=>'unpaid','subtotal'=>(float)$quotation->subtotal-(float)$quotation->discount_total,'tax_total'=>$quotation->tax_total,'grand_total'=>$quotation->grand_total]);
            // Every quotation line carries over as-is, including manual
            // (non-product) lines — product_id stays null for those,
            // exactly like a manual line entered directly on an order.
            foreach($quotation->items as $item) $order->items()->create(['product_id'=>$item->product_id,'description'=>$item->description,'qty'=>$item->qty,'unit_price'=>$item->unit_price,'tax_amount'=>$item->tax_amount,'line_total'=>$item->line_total]);
            $quotation->update(['status'=>'converted']);
            ProductionJob::create(['job_number'=>$this->numbers->next('production_job'),'sales_order_id'=>$order->id,'due_date'=>$order->delivery_date,'stage'=>'waiting_for_design','sale_value'=>$order->grand_total,'estimated_profit'=>$order->grand_total]);
            return $order->load('items','customer','productionJob');
        });
    }

    public function orderToInvoice(SalesOrder $order): Invoice
    {
        return DB::transaction(function() use($order){
            if($order->invoices()->whereNotIn('status',['cancelled'])->exists()) throw new RuntimeException('An active invoice already exists for this order.');
            // Invoice number is directly derived from the Sales Order number
            // (never a separately-drawn sequence) so it's never possible for
            // one order's invoice to accidentally carry another order's
            // number — the relational sales_order_id FK is still the real
            // link; this formatted number is just for human readability.
            $invoiceNumber = 'INV-'.$order->order_number;
            $invoice=Invoice::create(['invoice_number'=>$invoiceNumber,'invoice_date'=>today(),'due_date'=>today()->addDays($order->customer->payment_terms_days),'customer_id'=>$order->customer_id,'sales_order_id'=>$order->id,'status'=>'sent','subtotal'=>$order->subtotal,'tax_total'=>$order->tax_total,'grand_total'=>$order->grand_total,'outstanding_amount'=>$order->grand_total,'posted_at'=>now()]);
            foreach($order->items as $item) $invoice->items()->create(['product_id'=>$item->product_id,'description'=>$item->description,'qty'=>$item->qty,'rate'=>$item->unit_price,'discount'=>max(0,round((float)$item->qty*(float)$item->unit_price-((float)$item->line_total-(float)$item->tax_amount),2)),'tax_amount'=>$item->tax_amount,'line_total'=>$item->line_total]);
            $lines=[
                ['account'=>'1100','debit'=>(float)$invoice->grand_total,'credit'=>0],
                ['account'=>'4000','debit'=>0,'credit'=>(float)$invoice->subtotal],
            ];
            if((float)$invoice->tax_total>0)$lines[]=['account'=>'2100','debit'=>0,'credit'=>(float)$invoice->tax_total];
            $this->accounting->post($invoice,"Invoice {$invoice->invoice_number}",$lines,(string)$invoice->invoice_date);
            return $invoice->load('items','customer');
        });
    }

    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function() use($invoice,$data){
            $amount=round((float)$data['amount'],2); if($amount<=0 || $amount>(float)$invoice->outstanding_amount) throw new RuntimeException('Payment must not exceed the outstanding balance.');
            $proofFields = [];
            if (!empty($data['proof_file'])) {
                $proofFields = $this->proofs->store($data['proof_file'], 'payment-proofs');
            }
            $payment=Payment::create(['payment_number'=>$this->numbers->next('payment'),'customer_id'=>$invoice->customer_id,'method'=>$data['method'],'amount'=>$amount,'payment_date'=>$data['payment_date'],'reference_number'=>$data['reference_number']??null,'notes'=>$data['notes']??null,'received_by'=>auth()->id()]+$proofFields);
            $payment->allocations()->create(['invoice_id'=>$invoice->id,'allocated_amount'=>$amount]);
            $invoice->amount_paid=round((float)$invoice->amount_paid+$amount,2); $invoice->outstanding_amount=round((float)$invoice->grand_total-(float)$invoice->amount_paid,2); $invoice->status=$invoice->outstanding_amount<=0?'paid':'partially_paid'; $invoice->save();
            $order=$invoice->salesOrder; if($order) $order->update(['payment_status'=>$invoice->status==='paid'?'paid':'partial','confirmation_status'=>'confirmed']);
            $cashAccount=in_array($data['method'],['cash','cod'],true)?'1000':'1010';
            $this->accounting->post($payment,"Payment {$payment->payment_number}",[['account'=>$cashAccount,'debit'=>$amount,'credit'=>0],['account'=>'1100','debit'=>0,'credit'=>$amount]],$data['payment_date']);
            return $payment;
        });
    }

    public function createDelivery(SalesOrder $order): DeliveryNote
    {
        if ($existing = DeliveryNote::where('sales_order_id', $order->id)->first()) {
            return $existing;
        }

        $preferredDate = $order->delivery_date ?: today()->addDays(5);
        $deliveryDate = $this->deliveryScheduler->nextAvailableDate($preferredDate);
        $packageSize = 'standard';
        $charge = $this->deliveryScheduler->charge($order->emirate ?: $order->customer->emirate, $packageSize);

        return DB::transaction(function () use ($order, $deliveryDate, $packageSize, $charge) {
            $delivery = DeliveryNote::create([
                'delivery_note_number' => $this->numbers->next('delivery_note'),
                'sales_order_id' => $order->id,
                'invoice_id' => $order->invoices()->latest()->value('id'),
                'customer_id' => $order->customer_id,
                'driver_id' => $order->driver_id,
                'delivery_date' => $deliveryDate,
                'status' => 'pending',
                'package_size' => $packageSize,
                'delivery_charge' => $charge,
                'last_updated_by' => auth()->id(),
            ]);
            $order->update([
                'delivery_date' => $deliveryDate,
                'delivery_status' => 'scheduled',
            ]);

            return $delivery;
        });
    }
}
