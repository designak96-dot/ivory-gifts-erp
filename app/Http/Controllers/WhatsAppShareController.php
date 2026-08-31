<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Services\SimpleWorkflowService;
use Illuminate\Support\Facades\Route;

/**
 * No WhatsApp API, no paid service anywhere in this flow. This
 * controller only ever does two things: (1) tells the browser whether
 * an invoice/proof exist yet, and (2) builds a wa.me URL — a plain link
 * that opens WhatsApp or WhatsApp Web with a pre-filled message. Staff
 * still has to press Send themselves inside WhatsApp; nothing here ever
 * sends a message automatically.
 */
class WhatsAppShareController extends Controller
{
    /** Checked before showing the WhatsApp button's flow — invoice is required, proof is optional. */
    public function check(SalesOrder $order)
    {
        $invoice = $order->invoices()->whereNotIn('status', ['cancelled'])->latest()->first();
        $proof = $order->attachments()->where('category', 'Confirmed Order Proof')->latest()->first();

        return response()->json([
            'has_invoice' => (bool) $invoice,
            'has_proof' => (bool) $proof,
            'generate_invoice_url' => route('orders.invoice', $order),
        ]);
    }

    /** Builds the actual wa.me link — only called once the invoice is confirmed to exist. */
    public function link(SalesOrder $order, \App\Services\ShareLinkGetOrCreator $links)
    {
        abort_unless(in_array($order->simple_status, ['ready', 'delivered']), 422, 'WhatsApp share is only available once the order is Ready or Delivered.');
        $invoice = $order->invoices()->whereNotIn('status', ['cancelled'])->latest()->first();
        abort_unless($invoice, 422, 'Invoice has not been generated for this order.');

        $hasProof = $order->attachments()->where('category', 'Confirmed Order Proof')->exists();
        $shareLink = $links->getOrCreate($order);
        $shareUrl = route('share.show', $shareLink->token);

        $customer = $order->customer;
        $phone = preg_replace('/\D+/', '', $order->customer_phone ?: $customer->phone ?: '');
        abort_if($phone === '', 422, 'This customer has no saved phone number.');

        $message = $this->buildMessage($order, $customer->name, $shareUrl, $hasProof);

        // Deliberately return the raw phone + message text rather than a
        // pre-built, server-encoded wa.me URL. The message is UTF-8
        // (Arabic + emoji), and while PHP's rawurlencode() output was
        // independently verified byte-correct, letting the browser build
        // the final URL itself (via encodeURIComponent, which is
        // natively guaranteed-correct for UTF-8 in every browser)
        // removes one more link in the chain between "text is correct"
        // and "WhatsApp displays it correctly."
        return response()->json([
            'phone' => $phone,
            'message' => $message,
            'share_url' => $shareUrl,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
    }

    private function buildMessage(SalesOrder $order, string $customerName, string $shareUrl, bool $hasProof): string
    {
        if ($order->simple_status === 'delivered') {
            return "مرحبا {$customerName}\n"
                . "تم تسليم طلبك بنجاح!\n"
                . "*الطلب {$order->order_number}*\n"
                . "يمكنك مشاهدة الفاتورة وتفاصيل الطلب هنا\n\n"
                . "Hi {$customerName}\n"
                . "Your order has been delivered!\n"
                . "*ORDER {$order->order_number}*\n"
                . "You can view your invoice & order details here\n"
                . "{$shareUrl}\n\n"
                . "شكراً لاختيارك لنا\n"
                . "Thank you for choosing us\n"
                . "*Ivory Gifts*";
        }

        // Ready
        return "مرحبا {$customerName}\n"
            . "طلبك جاهز!\n"
            . "*الطلب {$order->order_number}*\n"
            . "يمكنك مشاهدة الفاتورة وتفاصيل الطلب هنا\n\n"
            . "Hi {$customerName}\n"
            . "Your order is ready!\n"
            . "*ORDER {$order->order_number}*\n"
            . "You can view your invoice & order details here\n"
            . "{$shareUrl}\n\n"
            . "شكراً لاختيارك لنا\n"
            . "Thank you for choosing us\n"
            . "*Ivory Gifts*";
    }
}
