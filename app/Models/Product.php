<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends BusinessModel {
    use SoftDeletes;
    protected function casts(): array { return ['is_active'=>'boolean','sale_price'=>'decimal:2','cost_price'=>'decimal:2']; }
    public function category(){ return $this->belongsTo(ProductCategory::class); }
    public function taxRate(){ return $this->belongsTo(TaxRate::class); }
    public function stockItems(){ return $this->hasMany(StockItem::class); }
}
