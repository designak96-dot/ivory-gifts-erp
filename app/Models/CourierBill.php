<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CourierBill extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['bill_date' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'payment_date' => 'date', 'amount_ex_tax' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'aed_equivalent' => 'decimal:2']; }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function lines() { return $this->hasMany(CourierBillLine::class); }
    public function deliveries() { return $this->hasManyThrough(DeliveryNote::class, CourierBillLine::class, 'courier_bill_id', 'id', 'id', 'delivery_note_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function remainingAmount(): float { return round((float) $this->total_amount - (float) $this->amount_paid, 2); }
}
