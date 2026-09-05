<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryFinanceSetting extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['effective_date' => 'date', 'value' => 'decimal:2']; }

    /** The value in effect on the given date — never the "current" value blindly, so historical deliveries are never silently rewritten by a later rate change. */
    public static function valueOn(string $key, \Carbon\Carbon $date): float
    {
        return (float) (static::where('setting_key', $key)->whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')->value('value') ?? 0);
    }
}
