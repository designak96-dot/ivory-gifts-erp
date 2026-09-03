<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollPayment extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['payroll_month' => 'date', 'current_salary' => 'decimal:2', 'overtime_extra' => 'decimal:2', 'total_to_pay' => 'decimal:2', 'amount_paid' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'payment_date' => 'date']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function overtimeEntries() { return $this->hasMany(StaffOvertime::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function expense() { return Expense::where('source_type', 'payroll_payment')->where('source_id', $this->id)->first(); }
}
