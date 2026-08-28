<?php
namespace App\Models;
class PaymentAllocation extends BusinessModel { public function payment(){return $this->belongsTo(Payment::class);} public function invoice(){return $this->belongsTo(Invoice::class);} }
