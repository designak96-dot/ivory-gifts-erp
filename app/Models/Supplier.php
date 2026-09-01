<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Supplier extends BusinessModel { use SoftDeletes; public function rawMaterialPurchases(){ return $this->hasMany(RawMaterialPurchase::class); } }
