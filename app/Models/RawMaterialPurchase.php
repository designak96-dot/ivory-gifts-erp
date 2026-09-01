<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RawMaterialPurchase extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['purchase_date' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2']; }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function lines() { return $this->hasMany(RawMaterialPurchaseLine::class); }
    public function bankAccount() { return $this->belongsTo(ChartOfAccount::class, 'bank_account_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
