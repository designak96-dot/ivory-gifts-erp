<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffAttendance extends Model {
    protected $table = 'staff_attendance';
    protected $guarded = ['id'];
    protected function casts(): array { return ['date' => 'date']; }
    public function staff() { return $this->belongsTo(Staff::class); }
}
