<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                if (!Schema::hasTable('audit_logs') || $model instanceof AuditLog) {
                    return;
                }
                $old = $event === 'updated' ? array_intersect_key($model->getOriginal(), $model->getChanges()) : null;
                $new = in_array($event, ['created', 'updated'], true) ? ($event === 'created' ? $model->getAttributes() : $model->getChanges()) : null;
                AuditLog::create([
                    'auditable_type' => $model::class,
                    'auditable_id' => $model->getKey(),
                    'action' => $event,
                    'old_values' => $old,
                    'new_values' => $new,
                    'user_id' => Auth::id(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            });
        }
    }
}
