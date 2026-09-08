<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CourierBillPayment extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['payment_date' => 'date', 'amount' => 'decimal:2']; }
    public function courierBill() { return $this->belongsTo(CourierBill::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
