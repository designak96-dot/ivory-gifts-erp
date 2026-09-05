<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CourierBillLine extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['estimated_cost' => 'decimal:2', 'actual_billed_cost' => 'decimal:2']; }
    public function courierBill() { return $this->belongsTo(CourierBill::class); }
    public function delivery() { return $this->belongsTo(DeliveryNote::class, 'delivery_note_id'); }
    public function difference(): float { return round((float) $this->actual_billed_cost - (float) $this->estimated_cost, 2); }
}
