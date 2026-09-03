<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffLeave extends Model {
    protected $table = 'staff_leaves';
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'return_date' => 'date', 'days' => 'decimal:1']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
