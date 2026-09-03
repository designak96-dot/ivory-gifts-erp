<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffGratuity extends Model {
    protected $table = 'staff_gratuity';
    protected $guarded = ['id'];
    protected function casts(): array { return ['joining_date' => 'date', 'last_working_date' => 'date', 'payment_date' => 'date', 'estimated_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'remaining_amount' => 'decimal:2']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function expense() { return Expense::where('source_type', 'staff_gratuity')->where('source_id', $this->id)->first(); }
}
