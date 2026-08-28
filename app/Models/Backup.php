<?php
namespace App\Models;
class Backup extends BusinessModel { protected function casts():array{return ['started_at'=>'datetime','completed_at'=>'datetime'];} }
