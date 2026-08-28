<?php
namespace App\Models;
class QuotationVersion extends BusinessModel { protected function casts():array{return ['snapshot'=>'array'];} public function quotation(){return $this->belongsTo(Quotation::class);} }
