<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class PurchaseOrder extends BusinessModel { use SoftDeletes; protected function casts():array{return ['order_date'=>'date','expected_delivery_date'=>'date'];} public function supplier(){return $this->belongsTo(Supplier::class);} public function items(){return $this->hasMany(PurchaseOrderItem::class);} }
