<?php

namespace App\Http\Controllers;

use App\Models\{Expense, Invoice};
use Illuminate\Http\Request;

/**
 * Purely a read-only aggregation over existing, already-computed
 * invoice/expense tax figures — never recalculates, never writes to any
 * historical record. Every number here is a straight SUM of a value that
 * was already correctly computed and stored at the time the invoice or
 * expense was created.
 */
class VatReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $invoices = Invoice::whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)->whereNotIn('status', ['cancelled']);
        $salesVat = (float) (clone $invoices)->sum('tax_total');
        $taxableSales = (float) (clone $invoices)->sum('subtotal');

        $expenses = Expense::whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to);
        $inputVat = (float) (clone $expenses)->sum('tax_amount');
        $taxableExpenses = (float) (clone $expenses)->sum('amount_ex_tax');

        return view('finance.vat', [
            'from' => $from,
            'to' => $to,
            'salesVat' => $salesVat,
            'inputVat' => $inputVat,
            'netVat' => $salesVat - $inputVat,
            'taxableSales' => $taxableSales,
            'taxableExpenses' => $taxableExpenses,
            'invoiceCount' => (clone $invoices)->count(),
            'expenseCount' => (clone $expenses)->count(),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $invoices = Invoice::whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)->whereNotIn('status', ['cancelled'])->with('customer')->get();

        $filename = "vat-report-{$from}-to-{$to}.csv";
        $callback = function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice Number', 'Date', 'Customer', 'Taxable Amount', 'VAT Amount', 'Total']);
            foreach ($invoices as $inv) {
                fputcsv($handle, [$inv->invoice_number, $inv->invoice_date->toDateString(), $inv->customer->name ?? '', $inv->subtotal, $inv->tax_total, $inv->grand_total]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
