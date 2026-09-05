<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleExpense extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['expense_date' => 'date', 'next_service_date' => 'date', 'amount_ex_tax' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2']; }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function allocations() { return $this->hasMany(VehicleExpenseAllocation::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function expense() { return Expense::where('source_type', 'vehicle_expense')->where('source_id', $this->id)->first(); }
}
