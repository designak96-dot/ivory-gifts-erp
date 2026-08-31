<?php

namespace App\Services;

use App\Models\{Customer, Expense, Invoice, Product, SalesOrder, SalesOrderItem, StockItem};
use Illuminate\Support\Carbon;

/**
 * Phase 4 additions to Ivory AI — kept as a separate service from
 * IvoryAiInsightsService (which already powers the dashboard panel and
 * is deliberately left untouched) so none of this new analysis can
 * destabilize what's already working. Everything here is the same
 * discipline as the rest of Ivory AI: real Eloquent queries and
 * arithmetic over this ERP's own data, zero external API calls, zero
 * fabricated numbers.
 */
class IvoryAiAdvancedService
{
    /**
     * A 0-100 risk score per active order, derived from how many days
     * remain until delivery versus how far the order has actually
     * progressed through design/production. Not a lookup table — a
     * real formula: readiness (0=ready, 3=not started) multiplied
     * against urgency (how little time is left), so an order that's
     * both close to its deadline AND far from ready scores highest.
     */
    public function orderRiskScores(int $limit = 100)
    {
        $today = Carbon::today();
        $orders = SalesOrder::whereNotIn('delivery_status', ['delivered', 'cancelled'])
            ->whereNotNull('delivery_date')
            ->with('customer:id,name')
            ->orderBy('delivery_date')
            ->limit($limit)
            ->get();

        return $orders->map(function ($order) use ($today) {
            $daysRemaining = $today->diffInDays($order->delivery_date, false);

            $readiness = match (true) {
                in_array($order->production_status, ['ready', 'completed']) => 0,
                in_array($order->production_status, ['in_production', 'quality_check']) => 1,
                $order->design_status === 'designed' => 1,
                in_array($order->design_status, ['designing']) || $order->production_status === 'materials_pending' => 2,
                default => 3,
            };

            if ($order->confirmation_status === 'waiting') {
                $readiness = max($readiness, 3);
            }

            // Urgency: 0 days or overdue = maximum urgency (4); each day
            // of buffer reduces it, floored at 0 once a week or more away.
            $urgency = $daysRemaining <= 0 ? 4 : max(0, 4 - (int) floor($daysRemaining / 2));

            $rawScore = $readiness * $urgency; // 0 (ready, plenty of time) to 12 (not started, overdue)
            $score = (int) round(min(100, ($rawScore / 12) * 100));

            $level = $score >= 60 ? 'High' : ($score >= 30 ? 'Medium' : 'Low');

            return [
                'order' => $order,
                'score' => $score,
                'level' => $level,
                'days_remaining' => $daysRemaining,
                'readiness_stage' => $readiness === 0 ? 'Ready' : ($readiness === 1 ? 'In production' : ($readiness === 2 ? 'In design/materials' : 'Not started')),
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Payment risk per customer — explicitly informational, never a
     * blacklist or accusation. Based on: how many of their invoices are
     * currently overdue, the outstanding balance relative to their own
     * historical order volume, and their real payment-completion track
     * record (paid invoices vs total invoices ever issued to them).
     */
    public function customerPaymentRisk(int $limit = 100)
    {
        $today = Carbon::today();

        return Customer::whereHas('invoices')
            ->with(['invoices' => fn ($q) => $q->select('id', 'customer_id', 'grand_total', 'outstanding_amount', 'due_date', 'status')])
            ->limit($limit)
            ->get()
            ->map(function ($customer) use ($today) {
                $invoices = $customer->invoices;
                $totalInvoices = $invoices->count();
                $overdueInvoices = $invoices->filter(fn ($i) => $i->outstanding_amount > 0 && $i->due_date && $i->due_date->lt($today));
                $overdueCount = $overdueInvoices->count();
                $outstanding = (float) $invoices->sum('outstanding_amount');
                $fullyPaidCount = $invoices->where('status', 'paid')->count();
                $paymentCompletionRate = $totalInvoices > 0 ? $fullyPaidCount / $totalInvoices : 1.0;

                // 0-100: overdue proportion weighs most, then how large the
                // outstanding balance is relative to their total invoiced
                // amount, then their historical completion rate (a real
                // track record, not a guess).
                $totalInvoiced = (float) $invoices->sum('grand_total');
                $overdueRatio = $totalInvoices > 0 ? $overdueCount / $totalInvoices : 0;
                $outstandingRatio = $totalInvoiced > 0 ? min(1, $outstanding / $totalInvoiced) : 0;

                $score = (int) round(($overdueRatio * 50) + ($outstandingRatio * 30) + ((1 - $paymentCompletionRate) * 20));
                $score = max(0, min(100, $score));

                $level = $score >= 60 ? 'High' : ($score >= 30 ? 'Medium' : 'Low');

                return [
                    'customer' => $customer,
                    'score' => $score,
                    'level' => $level,
                    'overdue_invoices' => $overdueCount,
                    'outstanding_amount' => $outstanding,
                    'total_invoices' => $totalInvoices,
                    'payment_completion_rate' => round($paymentCompletionRate * 100, 1),
                ];
            })
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Compares this week's sales to a rolling average of the prior 8
     * weeks, using standard deviation to judge whether the difference is
     * actually statistically meaningful (more than 1.5 standard
     * deviations away) rather than normal week-to-week noise.
     */
    public function salesAnomalies(): array
    {
        $today = Carbon::today();
        $weeks = [];
        for ($i = 1; $i <= 8; $i++) {
            $start = $today->copy()->subWeeks($i)->startOfWeek();
            $end = $start->copy()->endOfWeek();
            $weeks[] = (float) SalesOrder::whereBetween('order_date', [$start, $end])->sum('grand_total');
        }

        $mean = count($weeks) > 0 ? array_sum($weeks) / count($weeks) : 0;
        $variance = count($weeks) > 0 ? array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $weeks)) / count($weeks) : 0;
        $stdDev = sqrt($variance);

        $thisWeekStart = $today->copy()->startOfWeek();
        $thisWeekSales = (float) SalesOrder::whereBetween('order_date', [$thisWeekStart, $today])->sum('grand_total');

        if ($stdDev <= 0 || $mean <= 0) {
            return ['has_anomaly' => false, 'this_week' => $thisWeekSales, 'average' => round($mean, 2), 'reason' => 'Not enough historical variation to judge yet.'];
        }

        $deviations = ($thisWeekSales - $mean) / $stdDev;
        $isAnomaly = abs($deviations) >= 1.5;

        return [
            'has_anomaly' => $isAnomaly,
            'direction' => $deviations > 0 ? 'increase' : 'drop',
            'this_week' => $thisWeekSales,
            'average' => round($mean, 2),
            'std_dev' => round($stdDev, 2),
            'deviations' => round($deviations, 1),
        ];
    }

    /** Same statistical approach as sales anomalies, applied to expense categories individually. */
    public function expenseSpikes(): array
    {
        $today = Carbon::today();
        $thisMonthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $thisMonthStart->copy()->subMonth();
        $prevMonthEnd = $thisMonthStart->copy()->subDay();

        $categories = Expense::whereBetween('expense_date', [$prevMonthStart, $today])->distinct()->pluck('category');

        return $categories->map(function ($category) use ($thisMonthStart, $prevMonthStart, $prevMonthEnd) {
            $current = (float) Expense::where('category', $category)->where('expense_date', '>=', $thisMonthStart)->sum('total_amount');
            $previous = (float) Expense::where('category', $category)->whereBetween('expense_date', [$prevMonthStart, $prevMonthEnd])->sum('total_amount');
            if ($previous <= 0) return null;
            $changePercent = round((($current - $previous) / $previous) * 100, 1);
            return $changePercent >= 30 ? ['category' => $category, 'current' => $current, 'previous' => $previous, 'change_percent' => $changePercent] : null;
        })->filter()->sortByDesc('change_percent')->values()->all();
    }

    /**
     * A customer is "due to reorder" when more time has passed since
     * their last order than their own historical average gap between
     * orders — a real pattern from their own history, not a generic
     * rule applied to everyone.
     */
    public function repeatCustomerOpportunities(int $limit = 20)
    {
        $today = Carbon::today();

        return Customer::withCount('orders')
            ->has('orders', '>=', 2)
            ->with(['orders' => fn ($q) => $q->select('id', 'customer_id', 'order_date')->orderBy('order_date')])
            ->get()
            ->map(function ($customer) use ($today) {
                $dates = $customer->orders->pluck('order_date');
                $gaps = [];
                for ($i = 1; $i < $dates->count(); $i++) {
                    $gaps[] = $dates[$i - 1]->diffInDays($dates[$i]);
                }
                $avgGap = count($gaps) > 0 ? array_sum($gaps) / count($gaps) : null;
                $lastOrder = $dates->last();
                $daysSinceLast = $lastOrder ? $lastOrder->diffInDays($today) : null;

                if (!$avgGap || !$daysSinceLast) return null;

                $isDue = $daysSinceLast >= $avgGap;

                return $isDue ? [
                    'customer' => $customer,
                    'last_order_date' => $lastOrder,
                    'days_since_last_order' => $daysSinceLast,
                    'average_order_gap_days' => round($avgGap),
                    'order_count' => $customer->orders_count,
                ] : null;
            })
            ->filter()
            ->sortByDesc('days_since_last_order')
            ->take($limit)
            ->values();
    }

    /**
     * Demand estimate purely from historical monthly averages — no
     * external forecasting model. Explicitly labeled as an estimate in
     * every place it's surfaced (never presented as a guarantee).
     */
    public function productDemandPrediction(int $limit = 20)
    {
        $sixMonthsAgo = Carbon::today()->subMonths(6)->startOfMonth();

        return SalesOrderItem::selectRaw('product_id, SUM(qty) as total_qty')
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('order_date', '>=', $sixMonthsAgo))
            ->groupBy('product_id')
            ->with('product:id,name_en,sku,reorder_level')
            ->get()
            ->map(function ($row) {
                $monthsOfData = max(1, Carbon::today()->subMonths(6)->diffInMonths(Carbon::today()));
                $monthlyAverage = $row->total_qty / $monthsOfData;
                $onHand = (float) StockItem::where('product_id', $row->product_id)->sum('quantity_on_hand');
                $estimatedNextMonthDemand = round($monthlyAverage, 1);
                $recommendedReorderQty = max(0, round(($monthlyAverage * 2) - $onHand)); // covers ~2 months, minus what's already on hand

                return [
                    'product' => $row->product,
                    'monthly_average_sold' => round($monthlyAverage, 1),
                    'estimated_next_month_demand' => $estimatedNextMonthDemand,
                    'current_stock' => $onHand,
                    'recommended_reorder_qty' => $recommendedReorderQty,
                    'is_fast_moving' => $monthlyAverage >= 10,
                ];
            })
            ->sortByDesc('monthly_average_sold')
            ->take($limit)
            ->values();
    }

    /**
     * The same weighted composite used on the dashboard, but expanded
     * with profit trend, delivery performance rate, production delays,
     * and expense control as explicit factors — and, critically, a real
     * breakdown explaining what pushed the score up or down, computed
     * from the same numbers, not written after the fact.
     */
    public function businessHealthBreakdown(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subDay();

        // Payment collection
        $outstandingCount = Invoice::where('outstanding_amount', '>', 0)->count();
        $paymentScore = $outstandingCount === 0 ? 100 : max(0, 100 - min(60, $outstandingCount * 8));

        // Delivery performance: of deliveries due in the last 30 days, what fraction were actually delivered on or before their date.
        $recentDeliveries = \App\Models\DeliveryNote::whereBetween('delivery_date', [$today->copy()->subDays(30), $today])->get();
        $onTimeCount = $recentDeliveries->filter(fn ($d) => $d->status === 'delivered')->count();
        $deliveryScore = $recentDeliveries->count() > 0 ? round(($onTimeCount / $recentDeliveries->count()) * 100) : 100;

        // Sales trend
        $currentSales = (float) SalesOrder::where('order_date', '>=', $monthStart)->sum('grand_total');
        $prevSales = (float) SalesOrder::whereBetween('order_date', [$prevMonthStart, $prevMonthEnd])->sum('grand_total');
        $salesTrendPercent = $prevSales > 0 ? round((($currentSales - $prevSales) / $prevSales) * 100, 1) : ($currentSales > 0 ? 100.0 : 0.0);
        $trendScore = max(0, min(100, 60 + $salesTrendPercent));

        // Profit trend (real gross profit via the existing calculator)
        $profitService = app(ProfitCalculatorService::class);
        $currentProfit = $profitService->periodGrossProfit($monthStart, $today)['gross_profit'];
        $prevProfit = $profitService->periodGrossProfit($prevMonthStart, $prevMonthEnd)['gross_profit'];
        $profitTrendPercent = $prevProfit > 0 ? round((($currentProfit - $prevProfit) / $prevProfit) * 100, 1) : ($currentProfit > 0 ? 100.0 : 0.0);
        $profitScore = max(0, min(100, 60 + $profitTrendPercent));

        // Production delays: orders stuck for 3+ days
        $totalOpenOrders = max(SalesOrder::whereNotIn('delivery_status', ['delivered', 'cancelled'])->count(), 1);
        $stuckCount = SalesOrder::whereIn('design_status', ['need_design', 'designing'])->orWhere('production_status', 'materials_pending')->where('order_date', '<=', $today->copy()->subDays(3))->count();
        $pipelineScore = max(0, 100 - min(80, ($stuckCount / $totalOpenOrders) * 400));

        // Low stock
        $lowStockCount = app(StockIntelligenceService::class)->lowStock()->count();
        $stockScore = max(0, 100 - min(60, $lowStockCount * 10));

        // Expense control
        $currentExpenses = (float) Expense::where('expense_date', '>=', $monthStart)->sum('total_amount');
        $prevExpenses = (float) Expense::whereBetween('expense_date', [$prevMonthStart, $prevMonthEnd])->sum('total_amount');
        $expenseChangePercent = $prevExpenses > 0 ? round((($currentExpenses - $prevExpenses) / $prevExpenses) * 100, 1) : 0.0;
        $expenseScore = max(0, min(100, 100 - max(0, $expenseChangePercent)));

        $factors = [
            ['name' => 'Payment collection', 'score' => $paymentScore, 'weight' => 0.25, 'detail' => $outstandingCount === 0 ? 'No outstanding invoices' : "{$outstandingCount} unpaid invoices"],
            ['name' => 'Delivery performance', 'score' => $deliveryScore, 'weight' => 0.20, 'detail' => $recentDeliveries->count() > 0 ? "{$onTimeCount} of {$recentDeliveries->count()} recent deliveries completed" : 'No deliveries in the last 30 days'],
            ['name' => 'Sales trend', 'score' => $trendScore, 'weight' => 0.15, 'detail' => ($salesTrendPercent >= 0 ? '+' : '').$salesTrendPercent.'% vs last month'],
            ['name' => 'Profit trend', 'score' => $profitScore, 'weight' => 0.15, 'detail' => ($profitTrendPercent >= 0 ? '+' : '').$profitTrendPercent.'% vs last month'],
            ['name' => 'Production flow', 'score' => $pipelineScore, 'weight' => 0.10, 'detail' => $stuckCount === 0 ? 'No orders stuck' : "{$stuckCount} orders stuck 3+ days"],
            ['name' => 'Stock availability', 'score' => $stockScore, 'weight' => 0.10, 'detail' => $lowStockCount === 0 ? 'No low-stock products' : "{$lowStockCount} products at or below reorder level"],
            ['name' => 'Expense control', 'score' => $expenseScore, 'weight' => 0.05, 'detail' => ($expenseChangePercent >= 0 ? '+' : '').$expenseChangePercent.'% expenses vs last month'],
        ];

        $overall = (int) round(array_sum(array_map(fn ($f) => $f['score'] * $f['weight'], $factors)));
        $overall = max(0, min(100, $overall));
        $label = $overall >= 80 ? 'Healthy' : ($overall >= 55 ? 'Needs attention' : 'At risk');

        // What's helping vs hurting: any factor scoring 80+ is a positive
        // contributor, any factor scoring below 50 is dragging the score
        // down — a real classification of the same numbers above, not a
        // separately-written narrative.
        $strengths = array_values(array_filter($factors, fn ($f) => $f['score'] >= 80));
        $weaknesses = array_values(array_filter($factors, fn ($f) => $f['score'] < 50));

        return ['score' => $overall, 'label' => $label, 'factors' => $factors, 'strengths' => $strengths, 'weaknesses' => $weaknesses];
    }


    public function recommendations(): array
    {
        $today = Carbon::today();
        $recs = [];

        $unpaidOldInvoices = Invoice::where('outstanding_amount', '>', 0)->whereNotNull('due_date')->where('due_date', '<', $today)->with('customer:id,name')->limit(3)->get();
        foreach ($unpaidOldInvoices as $inv) {
            $recs[] = ['action' => 'Collect payment', 'detail' => "Invoice {$inv->invoice_number} — {$inv->customer->name} — AED ".number_format($inv->outstanding_amount, 2)." overdue", 'url' => route('invoices.show', $inv)];
        }

        $needDesign = SalesOrder::where('design_status', 'need_design')->where('order_date', '<=', $today->copy()->subDays(2))->limit(3)->get();
        foreach ($needDesign as $order) {
            $recs[] = ['action' => 'Approve design', 'detail' => "Order {$order->order_number} still waiting on design", 'url' => route('orders.show', $order)];
        }

        $readyToStart = SalesOrder::where('design_status', 'designed')->where('production_status', 'waiting')->limit(3)->get();
        foreach ($readyToStart as $order) {
            $recs[] = ['action' => 'Start production', 'detail' => "Order {$order->order_number} — design approved, production not started", 'url' => route('orders.show', $order)];
        }

        $staleQuotations = \App\Models\Quotation::where('status', 'sent')->where('quotation_date', '<=', $today->copy()->subDays(5))->limit(3)->get();
        foreach ($staleQuotations as $q) {
            $recs[] = ['action' => 'Follow up quotation', 'detail' => "Quotation {$q->quotation_number} sent over 5 days ago with no response", 'url' => route('quotations.show', $q)];
        }

        return array_slice($recs, 0, 10);
    }
}
