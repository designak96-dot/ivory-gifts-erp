<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffSalaryChange extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['effective_date' => 'date', 'previous_salary' => 'decimal:2', 'new_salary' => 'decimal:2']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
