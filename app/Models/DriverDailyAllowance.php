<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DriverDailyAllowance extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['allowance_date' => 'date', 'amount' => 'decimal:2']; }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function settlement() { return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id'); }
}
