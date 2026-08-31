<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Customer extends BusinessModel {
    use SoftDeletes;
    public function quotations() { return $this->hasMany(Quotation::class); }
    public function orders() { return $this->hasMany(SalesOrder::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function tags() { return $this->belongsToMany(Tag::class); }
    public function getWhatsappUrlAttribute(): ?string { $n=preg_replace('/\D+/', '', (string)$this->whatsapp); return $n ? 'https://wa.me/'.$n : null; }
}
