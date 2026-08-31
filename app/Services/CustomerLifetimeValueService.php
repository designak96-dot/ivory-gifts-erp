<?php

namespace App\Services;

use App\Models\{Customer, Invoice};

class CustomerLifetimeValueService
{
    public function __construct(private ProfitCalculatorService $profitCalculator) {}

    public function forCustomer(Customer $customer): array
    {
        $orders = $customer->orders()->with('items.product')->get();
        $totalOrders = $orders->count();
        $totalRevenue = (float) $orders->sum('grand_total');

        $totalProfit = 0.0;
        foreach ($orders as $order) {
            $totalProfit += $this->profitCalculator->forOrder($order)['gross_profit'];
        }

        $firstOrder = $orders->sortBy('order_date')->first();
        $lastOrder = $orders->sortByDesc('order_date')->first();

        $frequencyDays = null;
        if ($totalOrders >= 2 && $firstOrder && $lastOrder) {
            $spanDays = $firstOrder->order_date->diffInDays($lastOrder->order_date);
            $frequencyDays = $spanDays > 0 ? round($spanDays / ($totalOrders - 1), 1) : 0.0;
        }

        $outstanding = (float) Invoice::where('customer_id', $customer->id)->sum('outstanding_amount');

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'total_profit' => $totalProfit,
            'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0,
            'first_order' => $firstOrder?->order_date,
            'last_order' => $lastOrder?->order_date,
            'order_frequency_days' => $frequencyDays,
            'outstanding_amount' => $outstanding,
        ];
    }
}
