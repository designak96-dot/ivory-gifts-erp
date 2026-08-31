<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerShareLink extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['is_active' => 'boolean', 'expires_at' => 'datetime', 'last_viewed_at' => 'datetime']; }

    public function order() { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** Str::random() uses PHP's random_bytes() under the hood — cryptographically secure, not predictable/sequential. */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function isUsable(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
