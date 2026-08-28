<?php
namespace App\Models;
class SalesOrderStatusHistory extends BusinessModel { protected $table='sales_order_status_history'; public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} public function changedBy(){return $this->belongsTo(User::class,'changed_by');} }
