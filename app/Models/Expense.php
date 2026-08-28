<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
class Expense extends BusinessModel { use SoftDeletes; protected function casts():array{return ['expense_date'=>'date'];} }
