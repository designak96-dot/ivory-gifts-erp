<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class CreditNote extends BusinessModel {
    use SoftDeletes;
    protected function casts(): array { return ['credit_date' => 'date']; }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function items() { return $this->hasMany(CreditNoteItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
