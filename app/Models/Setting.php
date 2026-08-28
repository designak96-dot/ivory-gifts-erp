<?php
namespace App\Models;
class Setting extends BusinessModel { public static function value(string $key, mixed $default = null): mixed { return static::where('key', $key)->value('value') ?? $default; } }
