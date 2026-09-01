<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BankStatementTransaction extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['txn_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'amount' => 'decimal:2', 'balance' => 'decimal:2']; }
    public function reconciliation() { return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id'); }
    public function matchedRecord()
    {
        if (!$this->matched_type || !$this->matched_id) return null;
        return match ($this->matched_type) {
            'payment' => Payment::find($this->matched_id),
            'expense' => Expense::find($this->matched_id),
            'account_transfer' => AccountTransfer::find($this->matched_id),
            default => null,
        };
    }
}
