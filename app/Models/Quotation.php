<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Quotation extends BusinessModel { use SoftDeletes; protected function casts():array{return ['quotation_date'=>'date','valid_until'=>'date'];} public function customer(){return $this->belongsTo(Customer::class);} public function items(){return $this->hasMany(QuotationItem::class);} public function versions(){return $this->hasMany(QuotationVersion::class);} public function salesperson(){return $this->belongsTo(User::class,'salesperson_id');} }
