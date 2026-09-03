<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IncomeRecord extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['income_date' => 'date', 'amount_ex_tax' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'proof_missing' => 'boolean']; }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
