<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataImportRow extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['existing_values' => 'array', 'incoming_values' => 'array']; }
    public function import(){ return $this->belongsTo(DataImport::class, 'data_import_id'); }
}
