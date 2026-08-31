<?php

namespace App\Services;

use App\Models\SalesOrder;

/**
 * Computes gross profit for a Sales Order from real, already-stored data
 * only — never estimates or fabricates a figure for a cost that isn't
 * actually tracked. This deliberately does NOT assume a delivery cost:
 * there is no dedicated delivery-cost field anywhere in this schema, so
 * "delivery cost" is only included when it has genuinely been logged as
 * a production job cost against the order (a real, existing table) —
 * otherwise it's correctly treated as zero and the UI says so, rather
 * than silently pretending a number is complete when it isn't.
 *
 * Purely a read-time calculation — never writes to or alters any stored
 * order/invoice total, so historical totals are never touched.
 */
class ProfitCalculatorService
{
    public function forOrder(SalesOrder $order): array
    {
        $order->loadMissing('items.product', 'productionJob.costs');

        $revenue = (float) $order->subtotal; // pre-VAT line value
        $productCost = 0.0;
        foreach ($order->items as $item) {
            $unitCost = (float) ($item->product->cost_price ?? 0);
            $productCost += $unitCost * (float) $item->qty;
        }

        // Sales Orders have no discount field anywhere in this schema
        // (checked both sales_orders and sales_order_items directly) —
        // only Quotations track discount. Correctly reflecting that as
        // zero here rather than silently referencing a column that
        // doesn't exist.
        $otherCosts = (float) ($order->productionJob?->costs?->sum('amount') ?? 0);

        $grossProfit = $revenue - $productCost - $otherCosts;
        $margin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

        return [
            'revenue' => $revenue,
            'product_cost' => $productCost,
            'discount' => 0.0,
            'other_costs' => $otherCosts,
            'other_costs_tracked' => $order->productionJob?->costs?->isNotEmpty() ?? false,
            'gross_profit' => $grossProfit,
            'margin_percent' => $margin,
        ];
    }

    /** Efficient aggregate gross profit for a date range — one query, not a loop over individual orders. */
    public function periodGrossProfit(\Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $revenue = (float) SalesOrder::whereBetween('order_date', [$start, $end])->sum('subtotal');

        $productCost = (float) \App\Models\SalesOrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereBetween('order_date', [$start, $end]))
            ->whereNotNull('product_id')
            ->join('products', 'products.id', '=', 'sales_order_items.product_id')
            ->selectRaw('COALESCE(SUM(sales_order_items.qty * products.cost_price), 0) as total')
            ->value('total');

        $grossProfit = $revenue - $productCost;
        $margin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

        return ['revenue' => $revenue, 'product_cost' => $productCost, 'gross_profit' => $grossProfit, 'margin_percent' => $margin];
    }

    /** Aggregate profitability across all sold products, for the Product Profitability report. */
    public function productProfitability(?string $monthStart = null): \Illuminate\Support\Collection
    {
        $query = \App\Models\SalesOrderItem::query()
            ->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(qty) as qty_sold, SUM(line_total) as revenue');

        if ($monthStart) {
            $query->whereHas('order', fn ($q) => $q->where('order_date', '>=', $monthStart));
        }

        return $query->groupBy('product_id')
            ->with('product:id,name_en,sku,cost_price')
            ->get()
            ->map(function ($row) {
                $product = $row->product;
                $cost = (float) ($product?->cost_price ?? 0) * (float) $row->qty_sold;
                $revenue = (float) $row->revenue;
                $profit = $revenue - $cost;
                return [
                    'product' => $product,
                    'qty_sold' => (float) $row->qty_sold,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'gross_profit' => $profit,
                    'margin_percent' => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('gross_profit')
            ->values();
    }
}
