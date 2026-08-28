<?php
namespace App\Models;
class ChartOfAccount extends BusinessModel { public function lines(){return $this->hasMany(JournalLine::class,'account_id');} }
