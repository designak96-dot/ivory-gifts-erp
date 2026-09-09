<?php
declare(strict_types=1);
$root = __DIR__;
$layout = $root . '/resources/views/layouts/app.blade.php';
$partial = $root . '/resources/views/partials/_ready-whatsapp-js.blade.php';
$payload = $root . '/whatsapp-emoji-payload/_ready-whatsapp-js.blade.php';
$include = "@include('partials._ready-whatsapp-js')";
$guidancePath = $root . '/CLAUDE.md';
$guidancePayload = $root . '/whatsapp-emoji-payload/PRESERVE-WHATSAPP.md';
try {
    if (!is_file($root . '/artisan') || !is_file($layout) || !is_file($payload)) {
        throw new RuntimeException('Extract the ZIP inside the ERP directory containing artisan.');
    }
    $before = file_get_contents($layout);
    $script = file_get_contents($payload);
    if ($before === false || $script === false) throw new RuntimeException('Cannot read layout or payload.');
    $guidance = file_get_contents($guidancePayload);
    $priorGuidance = is_file($guidancePath) ? file_get_contents($guidancePath) : '';
    if ($guidance === false || $priorGuidance === false) throw new RuntimeException('Cannot read Claude preservation guidance.');
    $marker = '<!-- ivory-whatsapp-contract-v1 -->';
    $nextGuidance = strpos($priorGuidance, $marker) === false
        ? $priorGuidance . "\n\n" . $marker . "\n" . $guidance . "\n"
        : $priorGuidance;
    if (substr_count(strtolower($before), '</body>') !== 1) {
        throw new RuntimeException('Unexpected layout structure. No files changed.');
    }
    $after = $before;
    if (!preg_match('/@include\(\s*[\x27\x22]partials\._ready-whatsapp-js[\x27\x22]\s*\)/', $before)) {
        $after = preg_replace('/<\/body>/i', $include . "\n</body>", $before, 1);
    }
    $backup = $root . '/storage/app/whatsapp-backups/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0700, true)) throw new RuntimeException('Cannot create backup directory.');
    if (!copy($layout, $backup . '/app.blade.php')) throw new RuntimeException('Layout backup failed.');
    if (is_file($partial) && !copy($partial, $backup . '/_ready-whatsapp-js.blade.php')) {
        throw new RuntimeException('WhatsApp script backup failed.');
    }
    if (is_file($guidancePath) && !copy($guidancePath, $backup . '/CLAUDE.md')) {
        throw new RuntimeException('Claude guidance backup failed.');
    }
    if (file_put_contents($partial, $script, LOCK_EX) === false) throw new RuntimeException('Cannot install script.');
    if ($after !== $before && file_put_contents($layout, $after, LOCK_EX) === false) {
        throw new RuntimeException('Cannot add include. Backup: ' . $backup);
    }
    if ($nextGuidance !== $priorGuidance && file_put_contents($guidancePath, $nextGuidance, LOCK_EX) === false) {
        throw new RuntimeException('WhatsApp installed but Claude guidance could not be written. Backup: ' . $backup);
    }
    echo "Ready and Delivered Unicode emojis + direct WhatsApp Web installed.\nBackup: $backup\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
