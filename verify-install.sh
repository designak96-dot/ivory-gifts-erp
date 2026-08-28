#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CPANEL_HOME="$(dirname "$APP_DIR")"
PUBLIC_DIR="$CPANEL_HOME/public_html/accounts"
PHP_BIN="${PHP_BIN:-php}"
failed=0
check(){ label="$1"; shift; if "$@" >/dev/null 2>&1; then printf 'PASS  %s\n' "$label"; else printf 'FAIL  %s\n' "$label"; failed=1; fi; }
check 'Vendor dependencies' test -f "$APP_DIR/vendor/autoload.php"
check 'Public index bridge' test -f "$PUBLIC_DIR/index.php"
check 'Apache rewrite rules' test -f "$PUBLIC_DIR/.htaccess"
check 'Compiled CSS' test -f "$PUBLIC_DIR/build/assets/app.css"
check 'Compiled JavaScript' test -f "$PUBLIC_DIR/build/assets/app.js"
check 'Public storage link' test -L "$PUBLIC_DIR/storage"
"$PHP_BIN" "$APP_DIR/artisan" ivory:verify --no-interaction || failed=1
"$PHP_BIN" "$APP_DIR/artisan" route:list --except-vendor --no-ansi >/dev/null || failed=1
if [[ $failed -ne 0 ]]; then echo 'Verification failed.' >&2; exit 1; fi
echo 'All installation checks passed.'
