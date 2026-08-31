<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashAdjustment extends Model {
    protected $guarded = ['id'];
    public const TYPES = ['supplier_payment' => 'Supplier Cash Payment', 'refund' => 'Cash Refund', 'petty_cash' => 'Petty Cash', 'adjustment' => 'Approved Cash Adjustment'];
    protected function casts(): array { return ['adjustment_date' => 'date', 'amount' => 'decimal:2']; }
    public function cashAccount() { return $this->belongsTo(ChartOfAccount::class, 'cash_account_id'); }
    public function reconciliation() { return $this->belongsTo(CashReconciliation::class, 'cash_reconciliation_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
