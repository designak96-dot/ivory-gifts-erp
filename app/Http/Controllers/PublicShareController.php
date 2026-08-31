<?php

namespace App\Http\Controllers;

use App\Models\{CustomerShareLink, OrderAttachment};
use Illuminate\Support\Facades\Storage;

/**
 * Fully public, unauthenticated routes (rate-limited in routes/web.php).
 * Every method resolves access purely from the token — never from a
 * session, never from a guessable ID. Deliberately exposes only the
 * exact fields listed in the request: order number, status, customer
 * name, invoice number, total, paid, remaining, and the invoice/proof
 * documents themselves. Never profit, cost, staff comments, supplier
 * information, or audit history — none of that data is even loaded
 * here, so there's nothing to accidentally leak into the view.
 */
class PublicShareController extends Controller
{
    private function resolve(string $token): CustomerShareLink
    {
        $link = CustomerShareLink::where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 410, 'This link is no longer active.');
        return $link;
    }

    public function show(string $token)
    {
        $link = $this->resolve($token);
        $link->increment('view_count');
        $link->update(['last_viewed_at' => now()]);

        $order = $link->order()->with('customer')->first();
        $invoice = $order->invoices()->whereNotIn('status', ['cancelled'])->latest()->first();
        $proof = $order->attachments()->where('category', 'Confirmed Order Proof')->latest()->first();

        return view('share.show', [
            'token' => $token,
            'orderNumber' => $order->order_number,
            'status' => ucfirst($order->simple_status),
            'customerName' => $order->customer->name,
            'invoiceNumber' => $invoice?->invoice_number,
            'total' => $invoice?->grand_total ?? $order->grand_total,
            'paid' => $invoice?->amount_paid ?? 0,
            'remaining' => $invoice ? $invoice->outstanding_amount : $order->grand_total,
            'hasInvoice' => (bool) $invoice,
            'hasProof' => (bool) $proof,
        ]);
    }

    public function viewInvoice(string $token)
    {
        $link = $this->resolve($token);
        $order = $link->order;
        $invoice = $order->invoices()->whereNotIn('status', ['cancelled'])->latest()->firstOrFail();
        $invoice->load('items', 'customer');
        return view('share.invoice', compact('invoice'));
    }

    public function downloadProof(string $token)
    {
        $link = $this->resolve($token);
        $proof = $link->order->attachments()->where('category', 'Confirmed Order Proof')->latest()->firstOrFail();
        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);

        // 'inline' (top-level navigation, e.g. the modal iframe): images
        // get wrapped in a dedicated fit-to-screen view for guaranteed,
        // consistent behavior across browsers — PDFs are served directly
        // since browser PDF viewers already provide real fit-to-page and
        // zoom controls, and wrapping them again would create a
        // confusing nested-scroll experience.
        // 'raw': the actual file bytes, always — this is what the
        // wrapper view's own <img> tag requests, so it must never
        // re-trigger the wrapper logic itself.
        if (request()->boolean('inline') && str_starts_with($proof->mime, 'image/')) {
            return view('share.proof-image', ['imageUrl' => route('share.proof', $token).'?raw=1']);
        }
        if (request()->boolean('inline') || request()->boolean('raw')) {
            return response()->file(Storage::disk('local')->path($proof->file_path), ['Content-Type' => $proof->mime]);
        }
        return Storage::disk('local')->download($proof->file_path, $proof->original_name);
    }
}
