<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExpenseDeliveryAllocation extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['allocated_amount' => 'decimal:2']; }
    public function expense() { return $this->belongsTo(Expense::class); }
    public function delivery() { return $this->belongsTo(DeliveryNote::class, 'delivery_note_id'); }
}
