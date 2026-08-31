<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminder_enabled' => 'boolean']; }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function linkable() { return $this->morphTo(); }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'done' && $this->status !== 'cancelled' && $this->due_at && $this->due_at->isPast();
    }
}
