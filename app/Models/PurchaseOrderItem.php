<?php
namespace App\Models;
class PurchaseOrderItem extends BusinessModel { public function purchaseOrder(){return $this->belongsTo(PurchaseOrder::class);} public function product(){return $this->belongsTo(Product::class);} }
