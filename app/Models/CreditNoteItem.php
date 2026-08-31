<?php
namespace App\Models;
class CreditNoteItem extends BusinessModel {
    public function creditNote() { return $this->belongsTo(CreditNote::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
