<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DriverSettlement extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'payment_date' => 'date', 'delivery_fee_total' => 'decimal:2', 'allowance_total' => 'decimal:2', 'total_payable' => 'decimal:2', 'amount_paid' => 'decimal:2', 'remaining_amount' => 'decimal:2']; }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function deliveries() { return $this->hasMany(DeliveryNote::class, 'driver_settlement_id'); }
    public function dailyAllowances() { return $this->hasMany(DriverDailyAllowance::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
