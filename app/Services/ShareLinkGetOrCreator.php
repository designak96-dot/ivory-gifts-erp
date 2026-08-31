<?php

namespace App\Services;

use App\Models\{CustomerShareLink, SalesOrder};

/** First use creates a link; every later use reuses the same active one — never a new link per click. */
class ShareLinkGetOrCreator
{
    public function getOrCreate(SalesOrder $order): CustomerShareLink
    {
        $link = $order->shareLinks()->where('is_active', true)->first();
        if ($link) return $link;

        return $order->shareLinks()->create([
            'token' => CustomerShareLink::generateToken(),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
    }
}
