<?php
namespace App\Models;
class Role extends BusinessModel {
    public function permissions() { return $this->belongsToMany(Permission::class); }
    public function users() { return $this->belongsToMany(User::class); }
}
