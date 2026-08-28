<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    protected $guarded = [];

    public function rows()
    {
        return $this->hasMany(ProductImportRow::class);
    }
}
