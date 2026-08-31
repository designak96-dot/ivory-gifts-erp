<?php

namespace App\Http\Controllers;

use App\Models\{CustomerShareLink, SalesOrder};
use Illuminate\Http\Request;

class ShareLinkController extends Controller
{
    public function store(SalesOrder $order, \App\Services\ShareLinkGetOrCreator $links)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $link = $links->getOrCreate($order);
        return back()->with('success', 'Customer share link ready.')->with('share_link_url', route('share.show', $link->token));
    }

    /** Regeneration invalidates the old link and creates a new one — the old token stops working immediately. */
    public function regenerate(SalesOrder $order)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $order->shareLinks()->where('is_active', true)->update(['is_active' => false]);
        $link = $order->shareLinks()->create([
            'token' => CustomerShareLink::generateToken(),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
        return back()->with('success', 'Link regenerated — the previous link no longer works.');
    }

    public function toggle(CustomerShareLink $shareLink)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $shareLink->update(['is_active' => !$shareLink->is_active]);
        return back()->with('success', $shareLink->is_active ? 'Link enabled.' : 'Link disabled.');
    }

    public function setExpiry(Request $request, CustomerShareLink $shareLink)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $data = $request->validate(['expires_at' => 'nullable|date|after:now']);
        $shareLink->update(['expires_at' => $data['expires_at'] ?? null]);
        return back()->with('success', 'Expiry updated.');
    }

    public function index(Request $request)
    {
        $q = CustomerShareLink::with('order.customer')->latest();
        if ($request->filled('q')) {
            $search = $request->q;
            $q->whereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->status === 'active');
        }
        return view('share-links.index', ['links' => $q->paginate(30)]);
    }
}
