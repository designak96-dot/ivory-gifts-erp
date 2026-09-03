<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffDocument extends Model {
    protected $guarded = ['id'];
    public function staff() { return $this->belongsTo(Staff::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
