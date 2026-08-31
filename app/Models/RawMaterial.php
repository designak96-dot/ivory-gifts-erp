<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class RawMaterial extends BusinessModel {
    use SoftDeletes;
    protected function casts(): array { return ['current_stock' => 'decimal:3', 'reorder_level' => 'decimal:3', 'latest_cost' => 'decimal:4', 'is_active' => 'boolean']; }
    public function preferredSupplier() { return $this->belongsTo(Supplier::class, 'preferred_supplier_id'); }
    public function purchases() { return $this->hasMany(RawMaterialPurchase::class); }
}
