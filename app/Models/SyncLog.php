<?php
namespace App\Models;
class SyncLog extends BusinessModel { protected function casts():array{return ['payload'=>'array','synced_at'=>'datetime'];} }
