<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RawMaterialPurchaseLine extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function purchase() { return $this->belongsTo(RawMaterialPurchase::class, 'raw_material_purchase_id'); }
    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
}
