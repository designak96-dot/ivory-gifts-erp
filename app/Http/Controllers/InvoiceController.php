<?php
namespace App\Http\Controllers;
use App\Models\{Invoice, Payment};
use App\Services\{AccountingService, SalesWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class InvoiceController extends Controller
{
    public function index(){ $q=Invoice::with('customer');if($s=request('q'))$q->where(fn($x)=>$x->where('invoice_number','like',"%$s%")->orWhereHas('customer',fn($c)=>$c->where('name','like',"%$s%")));if(request('status'))$q->where('status',request('status'));if(request('min_outstanding'))$q->where('outstanding_amount','>=',(float)request('min_outstanding'));return view('invoices.index',['invoices'=>$q->latest()->paginate(25)]);}

    /**
     * A real, browsable place to find a payment and view its proof — the
     * only path that existed before was the Invoice's own Payment History
     * section, which was deliberately removed. Reachable from the same
     * "Invoices & Payments" nav item the app already had (it just never
     * actually showed payments before).
     */
    public function payments()
    {
        $q = \App\Models\Payment::with('customer', 'allocations.invoice')->latest('payment_date');
        if ($s = request('q')) {
            $q->where(fn ($x) => $x->where('payment_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        return view('invoices.payments', ['payments' => $q->paginate(25)]);
    }
    public function show(Invoice $invoice){$invoice->load('customer','items','salesOrder','allocations.payment');return view('invoices.show',compact('invoice'));}
    public function payment(Request $r,Invoice $invoice,SalesWorkflow $workflow){$d=$r->validate(['amount'=>'required|numeric|min:0.01','method'=>'required|in:cash,bank_transfer,card,link,cod,cheque,online,other','payment_date'=>'required|date','reference_number'=>'nullable|string|max:100','notes'=>'nullable|string','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192'],['proof.required'=>'A payment proof/receipt must be uploaded before this payment can be posted.']);$d['proof_file']=$r->file('proof');try{$workflow->recordPayment($invoice,$d);return back()->with('success','Payment recorded and allocated.');}catch(\Illuminate\Validation\ValidationException $e){throw $e;}catch(\Throwable $e){return back()->withErrors(['payment'=>$e->getMessage()]);}}

    /**
     * Owner-only. Refuses to delete an invoice that already has payments —
     * that would leave a payment allocation pointing at a deleted invoice.
     * Delete the payment first (which itself un-applies from the invoice)
     * if the invoice genuinely needs to go too.
     */
    public function destroy(Invoice $invoice, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('invoices.delete'), 403);

        if ((float) $invoice->amount_paid > 0) {
            return back()->withErrors(['delete' => 'This invoice has payments recorded against it and cannot be deleted. Delete the payment(s) first.']);
        }

        DB::transaction(function () use ($invoice, $accounting) {
            $accounting->reverse($invoice, "Invoice {$invoice->invoice_number} deleted");
            $invoice->delete();
        });

        return redirect()->route('invoices.index')->with('success', "Invoice {$invoice->invoice_number} deleted.");
    }

    /**
     * Owner-only. Deletes a payment, reverses its journal entry, removes
     * its allocation(s), and recalculates every invoice that payment had
     * been applied to — so amount_paid/outstanding_amount/status never
     * drift out of sync with the real transaction history.
     */
    public function destroyPayment(Payment $payment, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('payments.delete'), 403);

        DB::transaction(function () use ($payment, $accounting) {
            $accounting->reverse($payment, "Payment {$payment->payment_number} deleted");

            $affectedInvoices = $payment->allocations()->with('invoice')->get()->pluck('invoice')->unique('id');
            foreach ($payment->allocations as $allocation) {
                $allocation->delete();
            }
            foreach ($affectedInvoices as $invoice) {
                $paid = round((float) $invoice->allocations()->sum('allocated_amount'), 2);
                $invoice->amount_paid = $paid;
                $invoice->outstanding_amount = round((float) $invoice->grand_total - $paid, 2);
                $invoice->status = $invoice->outstanding_amount <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'sent');
                $invoice->save();
            }

            $payment->delete();
        });

        return back()->with('success', "Payment {$payment->payment_number} deleted and invoice balances recalculated.");
    }
}
