<?php

namespace App\Http\Controllers;

use App\Models\{CreditNote, Customer, Invoice, Product, SalesOrder};
use App\Services\{AccountingService, DocumentTotals};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    public function index()
    {
        return view('credit-notes.index', ['creditNotes' => CreditNote::with('customer', 'invoice')->latest()->paginate(25)]);
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('invoices.manage'), 403);
        $invoice = $request->query('invoice_id') ? Invoice::with('items', 'customer')->find($request->query('invoice_id')) : null;
        return view('credit-notes.create', [
            'invoice' => $invoice,
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name_en')->get(),
        ]);
    }

    public function store(Request $request, DocumentTotals $totals, AccountingService $accounting)
    {
        abort_unless(auth()->user()->hasPermission('invoices.manage'), 403);
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'reason' => 'required|string|max:500',
            'credit_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_id' => 'nullable|exists:products,id',
        ]);

        $calc = $totals->calculate($data['items']);

        // A credit note can never exceed the linked invoice's remaining
        // outstanding balance — that would create a negative amount owed,
        // which isn't a real, meaningful accounting state.
        if (!empty($data['invoice_id'])) {
            $invoice = Invoice::findOrFail($data['invoice_id']);
            if ($calc['grand_total'] > (float) $invoice->outstanding_amount + 0.01) {
                return back()->withErrors(['items' => 'Credit note total (AED '.number_format($calc['grand_total'], 2).') cannot exceed the invoice\'s outstanding balance (AED '.number_format($invoice->outstanding_amount, 2).').'])->withInput();
            }
        }

        $creditNote = DB::transaction(function () use ($data, $calc, $accounting) {
            $creditNote = CreditNote::create([
                'credit_note_number' => 'CN-'.now()->format('ym').'-'.str_pad((string) (CreditNote::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'credit_date' => $data['credit_date'],
                'reason' => $data['reason'],
                'subtotal' => $calc['subtotal'],
                'tax_total' => $calc['tax_total'],
                'grand_total' => $calc['grand_total'],
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            foreach ($calc['items'] as $item) {
                $creditNote->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'tax_amount' => $item['tax_amount'],
                    'line_total' => $item['line_total'],
                ]);
            }

            // Real double-entry: reduces Sales Revenue and VAT Output (a
            // credit note reverses part of the original sale), and
            // reduces what the customer owes (Accounts Receivable).
            $lines = [
                ['account' => '4000', 'debit' => $creditNote->subtotal, 'credit' => 0],
                ['account' => '1100', 'debit' => 0, 'credit' => $creditNote->grand_total],
            ];
            if ($creditNote->tax_total > 0) {
                $lines[] = ['account' => '2100', 'debit' => $creditNote->tax_total, 'credit' => 0];
            }
            $accounting->post($creditNote, "Credit note {$creditNote->credit_note_number}: {$creditNote->reason}", $lines, $creditNote->credit_date->toDateString());

            // Reduce the linked invoice's outstanding balance to reflect
            // the credit — never touches the invoice's original subtotal
            // /tax_total/grand_total (the historical amounts stay exactly
            // as invoiced).
            if ($creditNote->invoice_id) {
                $invoice = Invoice::find($creditNote->invoice_id);
                $invoice->outstanding_amount = max(0, round((float) $invoice->outstanding_amount - $creditNote->grand_total, 2));
                $invoice->status = $invoice->outstanding_amount <= 0 ? 'paid' : ($invoice->outstanding_amount < $invoice->grand_total ? 'partially_paid' : $invoice->status);
                $invoice->save();
            }

            return $creditNote;
        });

        return redirect()->route('credit-notes.show', $creditNote)->with('success', "Credit note {$creditNote->credit_note_number} posted.");
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load('customer', 'invoice', 'salesOrder', 'items.product', 'creator');
        return view('credit-notes.show', compact('creditNote'));
    }
}
