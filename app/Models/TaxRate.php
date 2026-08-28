<?php
namespace App\Models;
class TaxRate extends BusinessModel { protected function casts(): array { return ['rate'=>'decimal:4','is_inclusive'=>'boolean','is_active'=>'boolean']; } }
