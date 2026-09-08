<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Expense extends BusinessModel {
    use SoftDeletes;
    protected function casts():array{return ['expense_date'=>'date','delivery_day'=>'date'];}
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
}
