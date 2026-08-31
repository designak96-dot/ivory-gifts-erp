<?php

namespace App\Http\Controllers;

use App\Models\{OrderAttachment, SalesOrder};
use App\Services\ProofUploadService;
use Illuminate\Http\Request;

class OrderAttachmentController extends Controller
{
    /**
     * Dedicated endpoint for the compact "Confirmed Order Proof" widget
     * shown beside the order number in Sales/Deliveries lists. Uploading
     * always REPLACES any existing proof for this order (never
     * accumulates duplicates) — the same underlying storage as every
     * other order attachment, just with replace-on-upload semantics
     * specific to this one category, and gated to Confirmed orders only.
     */
    public function storeProof(Request $request, SalesOrder $order, ProofUploadService $uploads)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        abort_unless($order->simple_confirmation === 'confirmed', 422, 'The order must be Confirmed before uploading its proof.');
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192']);

        $existing = $order->attachments()->where('category', 'Confirmed Order Proof')->get();
        foreach ($existing as $old) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($old->file_path);
            $old->delete();
        }

        $stored = $uploads->store($request->file('file'), 'order-attachments');
        $attachment = $order->attachments()->create([
            'category' => 'Confirmed Order Proof',
            'file_path' => $stored['proof_path'],
            'original_name' => $stored['proof_original_name'],
            'mime' => $stored['proof_mime'],
            'size' => $stored['proof_size'],
            'uploaded_by' => auth()->id(),
        ]);
        \App\Models\SalesOrderStatusHistory::create(['sales_order_id' => $order->id, 'field' => 'attachment', 'old_value' => null, 'new_value' => 'Confirmed Order Proof uploaded ('.$stored['proof_original_name'].')', 'changed_by' => auth()->id()]);

        return response()->json(['id' => $attachment->id, 'original_name' => $attachment->original_name, 'view_url' => route('order-attachments.download', $attachment), 'delete_url' => route('order-attachments.destroy', $attachment)]);
    }

    public function store(Request $request, SalesOrder $order, ProofUploadService $uploads)
    {
        abort_unless(auth()->user()->hasPermission('orders.manage'), 403);
        $data = $request->validate([
            'category' => 'required|in:'.implode(',', OrderAttachment::CATEGORIES),
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);
        $stored = $uploads->store($request->file('file'), 'order-attachments');
        $order->attachments()->create([
            'category' => $data['category'],
            'file_path' => $stored['proof_path'],
            'original_name' => $stored['proof_original_name'],
            'mime' => $stored['proof_mime'],
            'size' => $stored['proof_size'],
            'uploaded_by' => auth()->id(),
        ]);
        \App\Models\SalesOrderStatusHistory::create(['sales_order_id' => $order->id, 'field' => 'attachment', 'old_value' => null, 'new_value' => $data['category'].' uploaded ('.$stored['proof_original_name'].')', 'changed_by' => auth()->id()]);
        return back()->with('success', 'Attachment uploaded.');
    }

    public function destroy(Request $request, OrderAttachment $attachment)
    {
        abort_unless(auth()->user()->hasPermission('orders.delete') || $attachment->uploaded_by === auth()->id(), 403);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['deleted' => true]);
        }
        return back()->with('success', 'Attachment removed.');
    }

    public function download(OrderAttachment $attachment)
    {
        abort_unless(auth()->user()->hasPermission('orders.view'), 403);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->file_path), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
