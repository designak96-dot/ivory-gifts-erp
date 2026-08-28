<?php
namespace App\Models;
class InvoiceItem extends BusinessModel { public function invoice(){return $this->belongsTo(Invoice::class);} public function product(){return $this->belongsTo(Product::class);} }
