<?php
namespace App\Models;
class StockMovement extends BusinessModel { public function stockItem(){return $this->belongsTo(StockItem::class);} public function reference(){return $this->morphTo();} }
