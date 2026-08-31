<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BankReconciliation extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['statement_month' => 'date', 'opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'total_credits' => 'decimal:2', 'total_debits' => 'decimal:2']; }
    public function bankAccount() { return $this->belongsTo(ChartOfAccount::class, 'bank_account_id'); }
    public function transactions() { return $this->hasMany(BankStatementTransaction::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
