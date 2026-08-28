<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Payment extends BusinessModel { use SoftDeletes; protected function casts():array{return ['payment_date'=>'date'];} public function customer(){return $this->belongsTo(Customer::class);} public function allocations(){return $this->hasMany(PaymentAllocation::class);} }
