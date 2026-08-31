<?php
namespace App\Models;
class OrderComment extends BusinessModel {
    public function order() { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function user() { return $this->belongsTo(User::class); }

    /** Extracts @username mentions from the body, matched against real, currently-active users only. */
    public function mentionedUsers()
    {
        preg_match_all('/@([a-zA-Z0-9._-]+)/', $this->body, $matches);
        if (empty($matches[1])) return collect();
        return User::where('is_active', true)
            ->get()
            ->filter(fn ($u) => collect($matches[1])->contains(fn ($handle) => str_starts_with(strtolower(str_replace(' ', '', $u->name)), strtolower($handle))));
    }
}
