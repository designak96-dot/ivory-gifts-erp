<?php
namespace App\Models;
class SalesOrderItem extends BusinessModel { protected function casts():array{return ['customisation'=>'array'];} public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} public function product(){return $this->belongsTo(Product::class);} }
