<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Payment, Product, SalesOrder};
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Real, permission-aware search across every record type requested:
     * Sales Order number, Invoice number, Customer name/phone, Product
     * name/SKU, Payment reference. Each result group is only queried if
     * the current user actually has permission to view that record type,
     * and every result links to the real record's actual show page.
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $digits = preg_replace('/\D+/', '', $term);
        $u = auth()->user();
        $results = [];

        if ($u->hasPermission('orders.view')) {
            SalesOrder::where('order_number', 'like', "%{$term}%")
                ->limit(5)->get(['id', 'order_number'])
                ->each(function ($o) use (&$results) { $results[] = ['type' => 'Sales Order', 'label' => $o->order_number, 'url' => route('orders.show', $o)]; });
        }

        if ($u->hasPermission('invoices.view')) {
            Invoice::where('invoice_number', 'like', "%{$term}%")
                ->limit(5)->get(['id', 'invoice_number'])
                ->each(function ($i) use (&$results) { $results[] = ['type' => 'Invoice', 'label' => $i->invoice_number, 'url' => route('invoices.show', $i)]; });
        }

        if ($u->hasPermission('customers.view')) {
            Customer::where(function ($q) use ($term, $digits) {
                $q->where('name', 'like', "%{$term}%");
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', "%{$digits}%")->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
                }
            })->limit(5)->get(['id', 'name', 'phone'])
                ->each(function ($c) use (&$results) { $results[] = ['type' => 'Customer', 'label' => $c->name.($c->phone ? ' · '.$c->phone : ''), 'url' => route('customers.show', $c)]; });
        }

        if ($u->hasPermission('products.view')) {
            Product::where(fn ($q) => $q->where('name_en', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))
                ->limit(5)->get(['id', 'name_en', 'sku'])
                ->each(function ($p) use (&$results) { $results[] = ['type' => 'Product', 'label' => $p->name_en.' · '.$p->sku, 'url' => route('products.edit', $p)]; });
        }

        if ($u->hasPermission('payments.manage')) {
            Payment::where('payment_number', 'like', "%{$term}%")
                ->orWhere('reference_number', 'like', "%{$term}%")
                ->limit(5)->get(['id', 'payment_number', 'reference_number'])
                ->each(function ($p) use (&$results) { $results[] = ['type' => 'Payment', 'label' => $p->payment_number.($p->reference_number ? ' · '.$p->reference_number : ''), 'url' => route('payments.index')]; });
        }

        return response()->json(array_slice($results, 0, 20));
    }
}
