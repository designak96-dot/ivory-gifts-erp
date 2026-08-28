<?php
namespace App\Models;
class ProductionJob extends BusinessModel { protected function casts():array{return ['due_date'=>'date'];} public function salesOrder(){return $this->belongsTo(SalesOrder::class);} public function designer(){return $this->belongsTo(User::class,'designer_id');} public function productionStaff(){return $this->belongsTo(User::class,'production_staff_id');} public function costs(){return $this->hasMany(ProductionJobCost::class);} }
