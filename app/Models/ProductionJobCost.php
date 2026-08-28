<?php
namespace App\Models;
class ProductionJobCost extends BusinessModel { public function job(){return $this->belongsTo(ProductionJob::class,'production_job_id');} }
