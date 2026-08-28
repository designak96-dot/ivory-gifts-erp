<?php
namespace App\Models;
class QuotationItem extends BusinessModel { public function quotation(){return $this->belongsTo(Quotation::class);} public function product(){return $this->belongsTo(Product::class);} }
