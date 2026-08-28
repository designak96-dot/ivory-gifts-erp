<?php
namespace App\Models;
class StockItem extends BusinessModel {
    public function product(){ return $this->belongsTo(Product::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function movements(){ return $this->hasMany(StockMovement::class); }
    public function getQuantityAvailableAttribute(): float { return (float)$this->quantity_on_hand-(float)$this->quantity_reserved; }
}
