<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DriverSettlementPayment extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['payment_date' => 'date', 'amount' => 'decimal:2', 'fee_portion' => 'decimal:2', 'allowance_portion' => 'decimal:2']; }
    public function settlement() { return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
