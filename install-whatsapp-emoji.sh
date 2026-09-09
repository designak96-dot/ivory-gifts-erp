#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "$0")"
PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" install-whatsapp-emoji.php
"$PHP_BIN" artisan view:clear
echo 'Finished. Hard-refresh the ERP with Ctrl+F5.'
