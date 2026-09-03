<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Staff extends BusinessModel {
    use SoftDeletes;
    protected $table = 'staff';
    protected function casts(): array { return ['joining_date' => 'date', 'current_salary' => 'decimal:2', 'passport_expiry' => 'date', 'visa_expiry' => 'date', 'emirates_id_expiry' => 'date']; }
    public function documents() { return $this->hasMany(StaffDocument::class); }
    public function salaryChanges() { return $this->hasMany(StaffSalaryChange::class)->orderByDesc('effective_date'); }
    public function payrollPayments() { return $this->hasMany(PayrollPayment::class)->orderByDesc('payroll_month'); }
    public function overtime() { return $this->hasMany(StaffOvertime::class)->orderByDesc('date'); }
    public function attendance() { return $this->hasMany(StaffAttendance::class)->orderByDesc('date'); }
    public function leaves() { return $this->hasMany(StaffLeave::class)->orderByDesc('start_date'); }
    public function tickets() { return $this->hasMany(StaffTicket::class)->orderByDesc('travel_date'); }
    public function gratuityRecords() { return $this->hasMany(StaffGratuity::class)->orderByDesc('created_at'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function payrollStatusFor(\Carbon\Carbon $month): string
    {
        $record = $this->payrollPayments()->whereDate('payroll_month', $month->copy()->startOfMonth())->first();
        return $record?->status ?? 'unpaid';
    }
}
