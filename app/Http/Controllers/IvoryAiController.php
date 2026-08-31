<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Product, SalesOrder, StockItem};
use App\Services\{IvoryAiAdvancedService, IvoryAiInsightsService, StockIntelligenceService};
use Illuminate\Http\Request;

class IvoryAiController extends Controller
{
    public function index(IvoryAiInsightsService $insights, IvoryAiAdvancedService $advanced)
    {
        return view('ivory-ai.index', [
            'summary' => $insights->build(),
            'health' => $advanced->businessHealthBreakdown(),
            'orderRisks' => $advanced->orderRiskScores(30),
            'paymentRisks' => $advanced->customerPaymentRisk(30),
            'salesAnomaly' => $advanced->salesAnomalies(),
            'expenseSpikes' => $advanced->expenseSpikes(),
            'slowMoving' => app(StockIntelligenceService::class)->slowMoving(),
            'repeatOpportunities' => $advanced->repeatCustomerOpportunities(),
            'demandPredictions' => $advanced->productDemandPrediction(),
            'recommendations' => $advanced->recommendations(),
        ]);
    }

    /**
     * The 8 quick-action buttons — each runs a real, direct database
     * query and returns real rows. No cached/precomputed "AI" output;
     * every click is a fresh query against current data.
     */
    public function quickAction(Request $request)
    {
        $action = $request->query('action');
        $today = now()->startOfDay();

        $result = match ($action) {
            'overdue-orders' => SalesOrder::whereNotIn('delivery_status', ['delivered', 'cancelled'])
                ->whereNotNull('delivery_date')->where('delivery_date', '<', $today)
                ->with('customer:id,name')->orderBy('delivery_date')->limit(50)->get()
                ->map(fn ($o) => ['label' => $o->order_number, 'sub' => $o->customer->name.' · due '.$o->delivery_date->format('d M Y'), 'url' => route('orders.show', $o)]),

            'unpaid-balances' => Invoice::where('outstanding_amount', '>', 0)
                ->with('customer:id,name')->orderByDesc('outstanding_amount')->limit(50)->get()
                ->map(fn ($i) => ['label' => $i->invoice_number, 'sub' => $i->customer->name.' · AED '.number_format($i->outstanding_amount, 2), 'url' => route('invoices.show', $i)]),

            'top-customers-month' => Customer::withCount(['orders' => fn ($q) => $q->where('order_date', '>=', now()->startOfMonth())])
                ->withSum(['orders as month_revenue' => fn ($q) => $q->where('order_date', '>=', now()->startOfMonth())], 'grand_total')
                ->get()->filter(fn ($c) => $c->orders_count > 0)->sortByDesc('month_revenue')->take(50)
                ->map(fn ($c) => ['label' => $c->name, 'sub' => $c->orders_count.' orders · AED '.number_format($c->month_revenue ?? 0, 2), 'url' => route('customers.show', $c)]),

            'orders-at-risk' => app(IvoryAiAdvancedService::class)->orderRiskScores(50)
                ->filter(fn ($r) => $r['level'] !== 'Low')
                ->map(fn ($r) => ['label' => $r['order']->order_number, 'sub' => $r['level'].' risk · '.$r['readiness_stage'], 'url' => route('orders.show', $r['order'])]),

            'low-stock' => Product::where('is_active', true)->where('reorder_level', '>', 0)->get()
                ->map(function ($p) {
                    $onHand = StockItem::where('product_id', $p->id)->sum('quantity_on_hand');
                    return $onHand <= $p->reorder_level ? ['label' => $p->name_en, 'sub' => $p->sku.' · '.$onHand.' left (reorder at '.$p->reorder_level.')', 'url' => route('products.edit', $p)] : null;
                })->filter()->values(),

            'slow-stock' => app(StockIntelligenceService::class)->slowMoving()
                ->map(fn ($r) => ['label' => $r['product']->name_en, 'sub' => $r['product']->sku.' · '.$r['on_hand'].' on hand, no sales in 60 days', 'url' => route('products.edit', $r['product'])]),

            'highest-profit-products' => app(\App\Services\ProfitCalculatorService::class)->productProfitability()->take(50)
                ->map(fn ($r) => ['label' => $r['product']?->name_en ?? '—', 'sub' => 'AED '.number_format($r['gross_profit'], 2).' profit · '.$r['margin_percent'].'% margin', 'url' => $r['product'] ? route('products.edit', $r['product']) : '#']),

            'customers-to-follow-up' => app(IvoryAiAdvancedService::class)->repeatCustomerOpportunities(50)
                ->map(fn ($r) => ['label' => $r['customer']->name, 'sub' => $r['days_since_last_order'].' days since last order (usually every '.$r['average_order_gap_days'].')', 'url' => route('customers.show', $r['customer'])]),

            default => collect(),
        };

        return response()->json(['action' => $action, 'count' => $result->count(), 'items' => $result->values()]);
    }
}
