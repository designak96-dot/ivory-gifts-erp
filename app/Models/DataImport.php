<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataImport extends Model
{
    protected $guarded = [];
    public function rows(){ return $this->hasMany(DataImportRow::class); }
}
