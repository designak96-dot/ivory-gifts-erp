<?php
namespace App\Models;
class ProductCategory extends BusinessModel { public function products(){ return $this->hasMany(Product::class,'category_id'); } }
