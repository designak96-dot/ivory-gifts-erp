<?php

namespace App\Models;

class DeliveryNote extends BusinessModel
{
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'delivered_at' => 'datetime',
            'delivery_charge' => 'decimal:2',
            'attempt_count' => 'integer',
        ];
    }

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'last_updated_by'); }
}
