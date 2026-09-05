<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model {
    protected $guarded = ['id'];
    public function expenses() { return $this->hasMany(VehicleExpense::class); }
}
