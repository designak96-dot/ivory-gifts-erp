<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

abstract class BusinessModel extends Model
{
    use Auditable;

    protected $guarded = ['id'];
}
