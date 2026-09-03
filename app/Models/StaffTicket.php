<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffTicket extends Model {
    protected $table = 'staff_tickets';
    protected $guarded = ['id'];
    protected function casts(): array { return ['travel_date' => 'date', 'return_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2']; }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function expense() { return Expense::where('source_type', 'staff_ticket')->where('source_id', $this->id)->first(); }
}
