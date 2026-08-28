<?php
namespace App\Models;
class JournalEntry extends BusinessModel { protected function casts():array{return ['entry_date'=>'date','posted_at'=>'datetime'];} public function lines(){return $this->hasMany(JournalLine::class);} public function reference(){return $this->morphTo();} }
