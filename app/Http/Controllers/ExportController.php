<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Expense, Invoice, Payment, Product, PurchaseOrder, SalesOrder};
use App\Services\ProfitCalculatorService;
use Illuminate\Http\Request;

/**
 * CSV export for every entity listed in the brief. XLSX/PDF are NOT
 * implemented: this environment has no PhpSpreadsheet/dompdf-style
 * library installed, and packagist.org isn't reachable from here to
 * safely install one mid-session — CSV (which opens directly in Excel
 * and any spreadsheet tool) is what's genuinely deliverable without
 * either faking a binary format or risking an unverified dependency
 * change to a production accounting system.
 */
class ExportController extends Controller
{
    public function index()
    {
        return view('exports.index');
    }

    private function stream(string $filename, array $headers, iterable $rows)
    {
        return response()->stream(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    public function orders(Request $request)
    {
        $orders = SalesOrder::with('customer')->orderByDesc('order_date')->get();
        return $this->stream('sales-orders.csv', ['Order Number', 'Customer', 'Order Date', 'Delivery Date', 'Status', 'Total'], $orders->map(fn ($o) => [$o->order_number, $o->customer->name ?? '', $o->order_date->toDateString(), $o->delivery_date?->toDateString(), $o->delivery_status, $o->grand_total]));
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::with('customer')->orderByDesc('invoice_date')->get();
        return $this->stream('invoices.csv', ['Invoice Number', 'Customer', 'Date', 'Due Date', 'Status', 'Subtotal', 'VAT', 'Total', 'Outstanding'], $invoices->map(fn ($i) => [$i->invoice_number, $i->customer->name ?? '', $i->invoice_date->toDateString(), $i->due_date?->toDateString(), $i->status, $i->subtotal, $i->tax_total, $i->grand_total, $i->outstanding_amount]));
    }

    public function payments(Request $request)
    {
        $payments = Payment::with('customer')->orderByDesc('payment_date')->get();
        return $this->stream('payments.csv', ['Payment Number', 'Customer', 'Date', 'Method', 'Reference', 'Amount'], $payments->map(fn ($p) => [$p->payment_number, $p->customer->name ?? '', $p->payment_date->toDateString(), $p->method, $p->reference_number, $p->amount]));
    }

    public function customers(Request $request)
    {
        $customers = Customer::orderBy('name')->get();
        return $this->stream('customers.csv', ['Code', 'Name', 'Phone', 'Email', 'Emirate', 'Status'], $customers->map(fn ($c) => [$c->customer_code, $c->name, $c->phone, $c->email, $c->emirate, $c->status]));
    }

    public function products(Request $request)
    {
        $products = Product::orderBy('name_en')->get();
        return $this->stream('products.csv', ['SKU', 'Name', 'Sale Price', 'Cost Price', 'Reorder Level', 'Active'], $products->map(fn ($p) => [$p->sku, $p->name_en, $p->sale_price, $p->cost_price, $p->reorder_level, $p->is_active ? 'Yes' : 'No']));
    }

    public function expenses(Request $request)
    {
        $expenses = Expense::orderByDesc('expense_date')->get();
        return $this->stream('expenses.csv', ['Expense Number', 'Date', 'Category', 'Payee', 'Amount ex-tax', 'VAT', 'Total'], $expenses->map(fn ($e) => [$e->expense_number, $e->expense_date->toDateString(), $e->category, $e->payee, $e->amount_ex_tax, $e->tax_amount, $e->total_amount]));
    }

    public function purchases(Request $request)
    {
        $purchases = PurchaseOrder::with('supplier')->orderByDesc('order_date')->get();
        return $this->stream('purchases.csv', ['PO Number', 'Supplier', 'Order Date', 'Status', 'Total'], $purchases->map(fn ($p) => [$p->purchase_order_number, $p->supplier->name ?? '', $p->order_date->toDateString(), $p->status, $p->grand_total]));
    }

    public function profit(Request $request, ProfitCalculatorService $calculator)
    {
        $rows = $calculator->productProfitability();
        return $this->stream('profit-report.csv', ['Product', 'SKU', 'Qty Sold', 'Revenue', 'Cost', 'Gross Profit', 'Margin %'], $rows->map(fn ($r) => [$r['product']?->name_en, $r['product']?->sku, $r['qty_sold'], $r['revenue'], $r['cost'], $r['gross_profit'], $r['margin_percent']]));
    }

    public function outstanding(Request $request)
    {
        $invoices = Invoice::with('customer')->where('outstanding_amount', '>', 0)->orderBy('due_date')->get();
        return $this->stream('outstanding-report.csv', ['Invoice Number', 'Customer', 'Due Date', 'Days Overdue', 'Outstanding Amount'], $invoices->map(fn ($i) => [$i->invoice_number, $i->customer->name ?? '', $i->due_date?->toDateString(), $i->due_date ? max(0, $i->due_date->diffInDays(today(), false)) : 0, $i->outstanding_amount]));
    }

    public function topCustomers(Request $request)
    {
        $customers = Customer::withCount('orders')->withSum('orders as revenue', 'grand_total')->get()->filter(fn ($c) => $c->orders_count > 0)->sortByDesc('revenue')->take(50);
        return $this->stream('top-customers.csv', ['Customer', 'Orders', 'Total Revenue'], $customers->map(fn ($c) => [$c->name, $c->orders_count, $c->revenue ?? 0]));
    }

    public function topProducts(Request $request)
    {
        $rows = \App\Models\SalesOrderItem::selectRaw('product_id, SUM(qty) as qty_sold, SUM(line_total) as revenue')
            ->whereNotNull('product_id')->groupBy('product_id')->orderByDesc('revenue')->with('product:id,name_en,sku')->limit(50)->get();
        return $this->stream('top-products.csv', ['Product', 'SKU', 'Qty Sold', 'Revenue'], $rows->map(fn ($r) => [$r->product?->name_en, $r->product?->sku, $r->qty_sold, $r->revenue]));
    }
}
