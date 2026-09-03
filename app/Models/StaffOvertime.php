<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffOvertime extends Model {
    protected $table = 'staff_overtime';
    protected $guarded = ['id'];
    protected function casts(): array { return ['date' => 'date', 'hours' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payrollPayment() { return $this->belongsTo(PayrollPayment::class); }
}
