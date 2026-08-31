<?php

declare(strict_types=1);

$appDir = $argv[1] ?? getcwd();
$appDir = rtrim((string) $appDir, DIRECTORY_SEPARATOR);

$files = [
    'app/Http/Controllers/WhatsAppShareController.php',
    'resources/views/layouts/app.blade.php',
    'resources/views/deliveries/_live.blade.php',
    'resources/views/deliveries/show.blade.php',
    'resources/views/orders/index.blade.php',
    'resources/views/orders/show.blade.php',
];

$original = [];
foreach ($files as $relative) {
    $path = $appDir . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$relative}\n");
        exit(2);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Could not read: {$relative}\n");
        exit(2);
    }
    $original[$relative] = $content;
}

$updated = $original;

// 1) Ready response: return structured data only. Delivered keeps the
// existing server-built message path completely unchanged.
$controllerOld = <<<'OLD'
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
OLD;

$controllerNew = <<<'NEW'
        if ($order->simple_status === 'ready') {
            return response()->json([
                'phone' => $phone,
                'customer_name' => $customer->name,
                'order_number' => $order->order_number,
                'share_url' => $shareUrl,
            ], 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
        }

        $message = $this->buildMessage($order, $customer->name, $shareUrl, $hasProof);

        // Delivered keeps the existing server-built message and browser
        // URL flow exactly as before. Only Ready is built client-side.
        return response()->json([
            'phone' => $phone,
            'message' => $message,
            'share_url' => $shareUrl,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
NEW;

if (!str_contains($updated['app/Http/Controllers/WhatsAppShareController.php'], "'customer_name' => \$customer->name")) {
    $count = 0;
    $updated['app/Http/Controllers/WhatsAppShareController.php'] = str_replace(
        $controllerOld,
        $controllerNew,
        $updated['app/Http/Controllers/WhatsAppShareController.php'],
        $count
    );
    if ($count !== 1) {
        fwrite(STDERR, "Controller patch did not match the expected latest-main code. No changes written.\n");
        exit(3);
    }
}

// 2) Load the Ready-only browser handler once from the shared app layout.
$layoutMarker = "@include('partials._ready-whatsapp-js')";
if (!str_contains($updated['resources/views/layouts/app.blade.php'], $layoutMarker)) {
    $count = 0;
    $updated['resources/views/layouts/app.blade.php'] = str_replace(
        '<script>window.IVORY_SYNC_URL=',
        $layoutMarker . "\n<script>window.IVORY_SYNC_URL=",
        $updated['resources/views/layouts/app.blade.php'],
        $count
    );
    if ($count !== 1) {
        fwrite(STDERR, "Layout patch anchor not found. No changes written.\n");
        exit(3);
    }
}

// 3) Mark only Ready/Delivered WhatsApp buttons with their current status.
// The new capture handler intercepts ONLY status=ready, so Delivered still
// reaches the original bootstrap.js listener untouched.
$buttonFiles = [
    'resources/views/deliveries/_live.blade.php' => 'check',
    'resources/views/deliveries/show.blade.php' => 'check',
    'resources/views/orders/index.blade.php' => 'check',
    'resources/views/orders/show.blade.php' => 'order-id',
];

foreach ($buttonFiles as $relative => $kind) {
    if (str_contains($updated[$relative], 'data-whatsapp-status="{{ $order->simple_status }}"')) {
        continue;
    }

    $old = $kind === 'order-id'
        ? 'data-whatsapp-share data-order-id='
        : 'data-whatsapp-share data-check-url=';
    $new = $kind === 'order-id'
        ? 'data-whatsapp-share data-whatsapp-status="{{ $order->simple_status }}" data-order-id='
        : 'data-whatsapp-share data-whatsapp-status="{{ $order->simple_status }}" data-check-url=';

    $count = 0;
    $updated[$relative] = str_replace($old, $new, $updated[$relative], $count);
    if ($count !== 1) {
        fwrite(STDERR, "WhatsApp button patch did not match exactly once in {$relative}. No changes written.\n");
        exit(3);
    }
}

// Write only after every expected latest-main anchor has been validated.
foreach ($updated as $relative => $content) {
    if ($content === $original[$relative]) {
        continue;
    }

    $path = $appDir . DIRECTORY_SEPARATOR . $relative;
    $tmp = $path . '.ready-whatsapp.tmp';
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        fwrite(STDERR, "Could not write temporary file for {$relative}.\n");
        exit(4);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fwrite(STDERR, "Could not replace {$relative}.\n");
        exit(4);
    }
}

fwrite(STDOUT, "Ready WhatsApp hotfix applied.\n");
