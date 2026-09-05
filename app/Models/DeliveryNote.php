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
            'customer_delivery_charge' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'driver_fee' => 'decimal:2',
            'allocated_phone_allowance' => 'decimal:2',
            'allocated_petrol_cost' => 'decimal:2',
            'allocated_maintenance_cost' => 'decimal:2',
            'shipment_date' => 'date',
            'expected_delivery_date' => 'date',
            'cost_exchange_rate_date' => 'date',
        ];
    }

    public function courierSupplier() { return $this->belongsTo(Supplier::class, 'courier_supplier_id'); }
    public function courierBill() { return $this->belongsTo(CourierBill::class); }
    public function driverSettlement() { return $this->belongsTo(DriverSettlement::class); }

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'last_updated_by'); }
}
