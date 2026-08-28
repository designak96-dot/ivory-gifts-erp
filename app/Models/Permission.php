<?php
namespace App\Models;
class Permission extends BusinessModel { public function roles() { return $this->belongsToMany(Role::class); } }
