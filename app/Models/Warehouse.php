<?php
namespace App\Models;
class Warehouse extends BusinessModel { protected function casts(): array { return ['is_active'=>'boolean']; } }
