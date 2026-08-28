<?php

namespace App\Http\Controllers;

use App\Models\{
    Customer,
    DeliveryNote,
    Expense,
    Invoice,
    Payment,
    ProductionJob,
    SalesOrder,
    SalesOrderItem
};
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $month = request('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $orders = SalesOrder::where('is_legacy_delivery_import', false)->whereBetween('order_date', [$start, $end]);

        $stats = [
            'orders' => (clone $orders)->count(),
            'sales' => (clone $orders)->sum('grand_total'),
            'unpaid' => Invoice::where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
            'customers' => Customer::count(),
            'due_today' => DeliveryNote::whereDate('delivery_date', today())->where('status', '!=', 'delivered')->count(),
            'production' => ProductionJob::whereNotIn('stage', ['completed', 'cancelled'])->count(),
            'expenses' => Expense::whereBetween('expense_date', [$start, $end])->sum('total_amount'),
        ];

        $recentOrders = SalesOrder::with('customer')->where('is_legacy_delivery_import', false)->latest()->limit(8)->get();
        $deliveries = DeliveryNote::with('customer', 'salesOrder')->whereDate('delivery_date', today())->get();

        $monthly = collect(range(5, 0))->map(function (int $offset) use ($start) {
            $period = $start->copy()->subMonths($offset);
            $periodEnd = $period->copy()->endOfMonth();
            $revenue = (float) SalesOrder::where('is_legacy_delivery_import', false)->whereBetween('order_date', [$period, $periodEnd])->sum('grand_total');
            $expenses = (float) Expense::whereBetween('expense_date', [$period, $periodEnd])->sum('total_amount');

            return [
                'label' => $period->format('M'),
                'full_label' => $period->format('M Y'),
                'revenue' => round($revenue, 2),
                'expenses' => round($expenses, 2),
                'profit' => round($revenue - $expenses, 2),
            ];
        })->values();

        $emirates = (clone $orders)->get(['emirate', 'grand_total'])
            ->groupBy(fn (SalesOrder $order) => trim((string) $order->emirate) ?: 'Other')
            ->map(fn ($items, string $label) => ['label' => $label, 'total' => round((float) $items->sum('grand_total'), 2)])
            ->sortByDesc('total')->values();

        $paymentMethods = Payment::whereBetween('payment_date', [$start, $end])->get(['method', 'amount'])
            ->groupBy(fn (Payment $payment) => trim((string) $payment->method) ?: 'Other')
            ->map(fn ($items, string $label) => [
                'label' => str($label)->replace('_', ' ')->title()->toString(),
                'total' => round((float) $items->sum('amount'), 2),
            ])->sortByDesc('total')->values();

        $topProducts = SalesOrderItem::with('product:id,name_en')
            ->whereHas('order', fn ($query) => $query->where('is_legacy_delivery_import', false)->whereBetween('order_date', [$start, $end]))
            ->get(['id', 'sales_order_id', 'product_id', 'description', 'line_total'])
            ->groupBy(fn (SalesOrderItem $item) => trim((string) ($item->product?->name_en ?: $item->description)) ?: 'Other')
            ->map(fn ($items, string $label) => ['label' => $label, 'total' => round((float) $items->sum('line_total'), 2)])
            ->sortByDesc('total')->take(5)->values();

        $chartData = [
            'monthly' => $monthly,
            'emirates' => $emirates,
            'payments' => $paymentMethods,
            'top_products' => $topProducts,
        ];

        return view('dashboard', compact('stats', 'recentOrders', 'deliveries', 'month', 'chartData'));
    }
}
