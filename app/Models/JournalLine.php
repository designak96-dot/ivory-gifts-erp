<?php
namespace App\Models;
class JournalLine extends BusinessModel { public function entry(){return $this->belongsTo(JournalEntry::class,'journal_entry_id');} public function account(){return $this->belongsTo(ChartOfAccount::class,'account_id');} }
