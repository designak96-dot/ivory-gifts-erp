<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class SalesOrder extends BusinessModel {
    use SoftDeletes;
    protected function casts():array{return ['order_date'=>'date','order_month'=>'date','delivery_date'=>'date','is_very_urgent'=>'boolean','is_legacy_delivery_import'=>'boolean'];}
    public function customer(){return $this->belongsTo(Customer::class);} public function quotation(){return $this->belongsTo(Quotation::class);} public function items(){return $this->hasMany(SalesOrderItem::class);} public function invoices(){return $this->hasMany(Invoice::class);} public function productionJob(){return $this->hasOne(ProductionJob::class);} public function deliveryNote(){return $this->hasOne(DeliveryNote::class);} public function driver(){return $this->belongsTo(User::class,'driver_id');} public function statusHistory(){return $this->hasMany(SalesOrderStatusHistory::class);}

    /**
     * Paid/remaining are always derived live from posted invoices/payments,
     * never a separately stored column — avoids the two ever drifting out
     * of sync with the real transaction history.
     */
    public function getPaidAmountAttribute(): float
    {
        return round((float) $this->invoices()->sum('amount_paid'), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return round((float) $this->grand_total - $this->paid_amount, 2);
    }

    public function getComputedPaymentStatusAttribute(): string
    {
        if ($this->paid_amount <= 0) return 'unpaid';
        if ($this->paid_amount < (float) $this->grand_total) return 'partially_paid';
        return 'paid';
    }
}
