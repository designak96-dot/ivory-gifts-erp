<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Invoice extends BusinessModel { use SoftDeletes; protected function casts():array{return ['invoice_date'=>'date','due_date'=>'date','posted_at'=>'datetime'];} public function customer(){return $this->belongsTo(Customer::class);} public function salesOrder(){return $this->belongsTo(SalesOrder::class);} public function items(){return $this->hasMany(InvoiceItem::class);} public function allocations(){return $this->hasMany(PaymentAllocation::class);} }
