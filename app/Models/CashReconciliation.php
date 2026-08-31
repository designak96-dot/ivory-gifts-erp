<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashReconciliation extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['reconciliation_date' => 'date', 'opening_cash' => 'decimal:2', 'cash_in' => 'decimal:2', 'cash_out' => 'decimal:2', 'expected_cash' => 'decimal:2', 'physical_cash_count' => 'decimal:2', 'difference' => 'decimal:2']; }
    public function cashAccount() { return $this->belongsTo(ChartOfAccount::class, 'cash_account_id'); }
    public function adjustments() { return $this->hasMany(CashAdjustment::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function hasDifference(): bool
    {
        return $this->physical_cash_count !== null && round((float) $this->difference, 2) !== 0.0;
    }
}
