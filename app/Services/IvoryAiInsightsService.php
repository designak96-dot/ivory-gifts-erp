<?php

namespace App\Services;

use App\Models\{DeliveryNote, Expense, Invoice, Product, SalesOrder, SalesOrderItem, StockItem};
use Illuminate\Support\Carbon;

/**
 * "Ivory AI" — despite the name, this is not a call to any AI model or
 * external API. It's a deterministic, rule-based scoring/insights engine
 * over the ERP's own real data (Laravel queries + arithmetic), exactly
 * as specified: "100% free, no external API... using only the existing
 * ERP database, Laravel calculations, rules and scoring."
 */
class IvoryAiInsightsService
{
    public function build(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subDay();

        $ordersNeedingAttention = $this->ordersNeedingAttention();
        $deliveriesAtRisk = $this->deliveriesAtRisk($today);
        $outstanding = $this->outstandingPayments();
        $stuckInProduction = $this->ordersStuckInDesignOrProduction();
        $lowStock = $this->lowStockProducts();
        $salesTrend = $this->salesTrend($monthStart, $prevMonthStart, $prevMonthEnd);
        $topCustomers = $this->topCustomers();
        $topProducts = $this->topSellingProducts($monthStart);
        $expenseWarning = $this->expenseWarning($monthStart, $prevMonthStart, $prevMonthEnd);

        $health = $this->businessHealthScore($salesTrend, $deliveriesAtRisk, $outstanding, $stuckInProduction);
        $trendConfidence = $this->trendConfidence();

        return [
            'health_score' => $health['score'],
            'health_label' => $health['label'],
            'orders_needing_attention' => $ordersNeedingAttention,
            'deliveries_at_risk' => $deliveriesAtRisk,
            'outstanding' => $outstanding,
            'stuck_in_production' => $stuckInProduction,
            'low_stock' => $lowStock,
            'sales_trend' => $salesTrend,
            'top_customers' => $topCustomers,
            'top_products' => $topProducts,
            'expense_warning' => $expenseWarning,
            'next_best_action' => $this->nextBestAction($deliveriesAtRisk, $ordersNeedingAttention, $stuckInProduction, $outstanding),
            'top_five_today' => $this->topFiveToday($deliveriesAtRisk, $ordersNeedingAttention, $stuckInProduction, $outstanding, $lowStock, $expenseWarning),
            'trend_confidence' => $trendConfidence,
        ];
    }

    /**
     * Confidence in the month-over-month sales trend, based on how many
     * distinct months of real order history actually exist to compare
     * against — not a fabricated percentage. 1 month of history is low
     * confidence (nothing to compare a trend against); 6+ months is high
     * confidence. Only shown where this kind of number is mathematically
     * grounded, per the explicit requirement.
     */
    private function trendConfidence(): ?int
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $expr = $driver === 'sqlite' ? "strftime('%Y-%m', order_date)" : 'DATE_FORMAT(order_date, "%Y-%m")';
        $monthsWithOrders = SalesOrder::selectRaw("COUNT(DISTINCT {$expr}) as c")->value('c');
        if (!$monthsWithOrders || $monthsWithOrders < 2) return null; // no meaningful trend possible yet
        return (int) round(min(100, ($monthsWithOrders / 6) * 100));
    }

    /**
     * Ranks every real concern this method already computed into a single
     * prioritized list, capped at 5. Priority order (most urgent first):
     * deliveries at risk (customer-facing, time-critical) > orders stuck
     * in the pipeline (named specifically) > overdue/unconfirmed orders >
     * unpaid invoices > low stock > expense warning. Each entry only
     * appears if the underlying real count/condition is actually > 0.
     */
    private function topFiveToday(array $deliveriesAtRisk, array $ordersNeedingAttention, array $stuckInProduction, array $outstanding, int $lowStock, ?array $expenseWarning): array
    {
        $items = [];

        if ($deliveriesAtRisk['count'] > 0) {
            $items[] = ['label' => "{$deliveriesAtRisk['count']} " . ($deliveriesAtRisk['count'] === 1 ? 'delivery is' : 'deliveries are') . ' due imminently', 'severity' => 'red'];
        }
        foreach (($stuckInProduction['orders'] ?? []) as $order) {
            $stage = $order->design_status !== 'ready' ? 'design' : 'production';
            $items[] = ['label' => "Order {$order->order_number} stuck in {$stage}", 'severity' => 'amber'];
        }
        if ($ordersNeedingAttention['count'] > 0) {
            $items[] = ['label' => "{$ordersNeedingAttention['count']} " . ($ordersNeedingAttention['count'] === 1 ? 'order needs' : 'orders need') . ' confirmation or is past its delivery date', 'severity' => 'amber'];
        }
        if ($outstanding['count'] > 0) {
            $items[] = ['label' => "AED " . number_format($outstanding['amount'], 2) . " outstanding across {$outstanding['count']} " . ($outstanding['count'] === 1 ? 'invoice' : 'invoices'), 'severity' => 'blue'];
        }
        if ($lowStock > 0) {
            $items[] = ['label' => "{$lowStock} " . ($lowStock === 1 ? 'product is' : 'products are') . ' at or below reorder level', 'severity' => 'amber'];
        }
        if ($expenseWarning) {
            $items[] = ['label' => "Expenses up {$expenseWarning['change_percent']}% vs last month", 'severity' => 'red'];
        }

        return array_slice($items, 0, 5);
    }

    /** Orders whose confirmation was never actioned, or whose delivery date has already passed while still unfulfilled. */
    private function ordersNeedingAttention(): array
    {
        $stale = SalesOrder::where('confirmation_status', 'waiting')
            ->where('order_date', '<=', Carbon::today()->subDays(2))
            ->count();
        $overdue = SalesOrder::where('delivery_status', '!=', 'delivered')
            ->whereNotNull('delivery_date')
            ->where('delivery_date', '<', Carbon::today())
            ->count();
        return ['count' => $stale + $overdue, 'stale_confirmation' => $stale, 'overdue_delivery' => $overdue];
    }

    /** Deliveries due today or tomorrow that aren't delivered yet, or have no driver assigned. */
    private function deliveriesAtRisk(Carbon $today): array
    {
        $q = DeliveryNote::whereNotNull('delivery_date')
            ->whereDate('delivery_date', '<=', $today->copy()->addDay())
            ->where('status', '!=', 'delivered');
        $count = (clone $q)->count();
        $unassigned = (clone $q)->whereNull('driver_id')->count();
        return ['count' => $count, 'unassigned' => $unassigned];
    }

    private function outstandingPayments(): array
    {
        $sum = (float) Invoice::where('outstanding_amount', '>', 0)->sum('outstanding_amount');
        $count = Invoice::where('outstanding_amount', '>', 0)->count();
        return ['amount' => $sum, 'count' => $count];
    }

    /** Orders that have sat in design or production without moving, for more than a few days. */
    private function ordersStuckInDesignOrProduction(): array
    {
        $cutoff = Carbon::today()->subDays(3);
        $stuck = SalesOrder::whereIn('design_status', ['need_design', 'in_progress'])
            ->orWhereIn('production_status', ['waiting', 'in_progress'])
            ->where('order_date', '<=', $cutoff)
            ->orderBy('order_date')
            ->limit(5)
            ->get(['id', 'order_number', 'design_status', 'production_status', 'order_date']);
        return ['count' => $stuck->count(), 'orders' => $stuck];
    }

    /** A product is low-stock when its total on-hand quantity across all warehouses falls at or below its own reorder level. */
    private function lowStockProducts(): int
    {
        return Product::where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->get(['id', 'reorder_level'])
            ->filter(function ($product) {
                $onHand = StockItem::where('product_id', $product->id)->sum('quantity_on_hand');
                return $onHand <= $product->reorder_level;
            })
            ->count();
    }

    private function salesTrend(Carbon $monthStart, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = (float) SalesOrder::where('order_date', '>=', $monthStart)->sum('grand_total');
        $previous = (float) SalesOrder::whereBetween('order_date', [$prevStart, $prevEnd])->sum('grand_total');
        $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100.0 : 0.0);
        return ['current' => $current, 'previous' => $previous, 'change_percent' => $change];
    }

    private function topCustomers(int $limit = 5)
    {
        return \App\Models\Customer::withCount('orders')
            ->withSum('orders as orders_sum_grand_total', 'grand_total')
            ->get()
            ->filter(fn ($c) => $c->orders_count >= 1)
            ->sortByDesc(fn ($c) => $c->orders_sum_grand_total ?? 0)
            ->take($limit)
            ->values();
    }

    private function topSellingProducts(Carbon $monthStart, int $limit = 5)
    {
        return SalesOrderItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(line_total) as total_revenue')
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('order_date', '>=', $monthStart))
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->with('product:id,name_en,sku')
            ->limit($limit)
            ->get();
    }

    private function expenseWarning(Carbon $monthStart, Carbon $prevStart, Carbon $prevEnd): ?array
    {
        $current = (float) Expense::where('expense_date', '>=', $monthStart)->sum('total_amount');
        $previous = (float) Expense::whereBetween('expense_date', [$prevStart, $prevEnd])->sum('total_amount');
        if ($previous <= 0) return null;
        $change = round((($current - $previous) / $previous) * 100, 1);
        if ($change < 20) return null; // only surface a warning when the rise is meaningful
        return ['current' => $current, 'previous' => $previous, 'change_percent' => $change];
    }

    /**
     * A weighted composite of four real signals, each normalized to 0-100
     * and capped, then combined. Weights are a simple, documented rule —
     * not a black box: payment collection health matters most for a
     * gifts/production business, then fulfillment risk, then sales
     * momentum, then whether orders are moving through the pipeline.
     */
    private function businessHealthScore(array $salesTrend, array $deliveriesAtRisk, array $outstanding, array $stuckInProduction): array
    {
        $totalOpenOrders = max(SalesOrder::count(), 1);

        $paymentScore = $outstanding['count'] === 0 ? 100 : max(0, 100 - min(60, $outstanding['count'] * 8));
        $deliveryScore = $deliveriesAtRisk['count'] === 0 ? 100 : max(0, 100 - min(70, $deliveriesAtRisk['count'] * 15));
        $trendScore = max(0, min(100, 60 + ($salesTrend['change_percent'] ?? 0)));
        $pipelineScore = max(0, 100 - min(80, ($stuckInProduction['count'] / $totalOpenOrders) * 400));

        $score = (int) round(($paymentScore * 0.35) + ($deliveryScore * 0.30) + ($trendScore * 0.20) + ($pipelineScore * 0.15));
        $score = max(0, min(100, $score));

        $label = $score >= 80 ? 'Healthy' : ($score >= 55 ? 'Needs attention' : 'At risk');

        return ['score' => $score, 'label' => $label];
    }

    private function nextBestAction(array $deliveriesAtRisk, array $ordersNeedingAttention, array $stuckInProduction, array $outstanding): string
    {
        if (($stuckInProduction['orders'] ?? null) && count($stuckInProduction['orders']) > 0) {
            $order = $stuckInProduction['orders'][0];
            $stage = $order->design_status !== 'ready' ? 'design approval' : 'production';
            return "Complete {$stage} for Order {$order->order_number} today.";
        }
        if ($deliveriesAtRisk['count'] > 0) {
            return "{$deliveriesAtRisk['count']} " . ($deliveriesAtRisk['count'] === 1 ? 'delivery is' : 'deliveries are') . ' due imminently — confirm driver assignment and route.';
        }
        if ($outstanding['count'] > 0) {
            return "Follow up on {$outstanding['count']} outstanding " . ($outstanding['count'] === 1 ? 'invoice' : 'invoices') . ' totalling AED ' . number_format($outstanding['amount'], 2) . '.';
        }
        if ($ordersNeedingAttention['count'] > 0) {
            return "{$ordersNeedingAttention['count']} " . ($ordersNeedingAttention['count'] === 1 ? 'order needs' : 'orders need') . ' confirmation or are past their delivery date.';
        }
        return 'No urgent actions — operations are on track.';
    }
}
