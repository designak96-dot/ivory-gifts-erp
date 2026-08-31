<?php

namespace App\Http\Controllers;

use App\Models\{OrderComment, SalesOrder};
use Illuminate\Http\Request;

class OrderCommentController extends Controller
{
    public function store(Request $request, SalesOrder $order)
    {
        $data = $request->validate(['body' => 'required|string|max:3000']);
        $order->comments()->create(['user_id' => auth()->id(), 'body' => $data['body']]);
        \App\Models\SalesOrderStatusHistory::create(['sales_order_id' => $order->id, 'field' => 'comment', 'old_value' => null, 'new_value' => auth()->user()->name.' added a comment', 'changed_by' => auth()->id()]);
        return back()->with('success', 'Comment added.');
    }

    /** Author, or a user with orders.delete (moderation authority — not orders.manage, which any salesperson holds), can edit. */
    public function update(Request $request, OrderComment $comment)
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->hasPermission('orders.delete'), 403);
        $data = $request->validate(['body' => 'required|string|max:3000']);
        $comment->update(['body' => $data['body'], 'edited_at' => now()]);
        return back()->with('success', 'Comment updated.');
    }

    /** Author, or a user with orders.delete, can delete. */
    public function destroy(OrderComment $comment)
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->hasPermission('orders.delete'), 403);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }
}
