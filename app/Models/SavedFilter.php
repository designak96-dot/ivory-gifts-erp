<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SavedFilter extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['params' => 'array']; }
}
