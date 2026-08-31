<?php

namespace App\Services;

use App\Models\{Product, SalesOrderItem, StockItem};

/**
 * Real stock-status classification from actual data. Note: this app has
 * no "sale"/"issue" stock_movements type (only 'adjustment' and
 * 'receipt' — Sales Orders don't deduct tracked inventory), so
 * "slow-moving" is defined here using actual sales_order_items activity
 * instead of movement records, since that's the real signal that
 * genuinely exists for "has this product actually been selling."
 */
class StockIntelligenceService
{
    public function lowStock()
    {
        return $this->productsWithOnHand(requireReorderLevel: true)
            ->filter(fn ($row) => $row['on_hand'] > 0 && $row['on_hand'] <= $row['product']->reorder_level)
            ->values();
    }

    public function outOfStock()
    {
        return $this->productsWithOnHand(requireReorderLevel: false)
            ->filter(fn ($row) => $row['on_hand'] <= 0)
            ->values();
    }

    /** Active products with stock on hand but no sales activity in the given window. */
    public function slowMoving(int $days = 60)
    {
        $cutoff = now()->subDays($days);
        $recentlySoldProductIds = SalesOrderItem::whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('order_date', '>=', $cutoff))
            ->distinct()
            ->pluck('product_id');

        return $this->productsWithOnHand(requireReorderLevel: false)
            ->filter(fn ($row) => $row['on_hand'] > 0 && !$recentlySoldProductIds->contains($row['product']->id))
            ->values();
    }

    private function productsWithOnHand(bool $requireReorderLevel)
    {
        $query = Product::where('is_active', true);
        if ($requireReorderLevel) {
            $query->where('reorder_level', '>', 0);
        }
        return $query->get()
            ->map(function ($product) {
                $onHand = (float) StockItem::where('product_id', $product->id)->sum('quantity_on_hand');
                return ['product' => $product, 'on_hand' => $onHand];
            });
    }
}
