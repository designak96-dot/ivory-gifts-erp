<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    protected $guarded = [];

    public function import()
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}
