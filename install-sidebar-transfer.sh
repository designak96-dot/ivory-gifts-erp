#!/usr/bin/env bash
set -euo pipefail
umask 027

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"
PHP_BIN="${PHP_BIN:-/opt/alt/php84/usr/bin/php}"
PATCH_FILE="$APP_DIR/account-transfer-sidebar.patch"
TARGET="resources/views/layouts/app.blade.php"

fail() { printf 'STOP: %s\n' "$*" >&2; exit 1; }
[[ -f artisan && -f "$TARGET" && -f routes/web.php ]] || fail 'Extract this update directly inside your ivory-accounts folder.'
[[ -f "$PATCH_FILE" ]] || fail 'account-transfer-sidebar.patch is missing.'
[[ -f vendor/autoload.php ]] || fail 'Existing PHP dependencies are missing. This package is not a full installer.'
[[ -x "$PHP_BIN" ]] || fail 'PHP was not found. Set PHP_BIN to your working PHP executable.'
command -v git >/dev/null 2>&1 || fail 'Git is required to apply this small patch safely.'
grep -Fq "name('finance.account-transfer')" routes/web.php || fail 'The existing account-transfer route is missing. No files were changed.'

if git apply --reverse --check "$PATCH_FILE" >/dev/null 2>&1; then
    printf 'Account Transfer sidebar link is already installed.\n'
else
    git apply --check --whitespace=error "$PATCH_FILE" || fail 'Your sidebar differs from the expected version. No files were changed; send this output for help.'
    mkdir -p "$APP_DIR/storage/app/private"
    BACKUP_DIR="$(mktemp -d "$APP_DIR/storage/app/private/sidebar-link-backup.XXXXXX")"
    cp -p "$TARGET" "$BACKUP_DIR/app.blade.php"
    git apply --whitespace=error "$PATCH_FILE"
    printf 'Sidebar backup saved to: %s/app.blade.php\n' "$BACKUP_DIR"
fi

"$PHP_BIN" artisan view:clear
printf '\nDone. Refresh your ERP: Finance > Account Transfer (below Bank & Cash).\n'
printf 'No database migrations, transfers, dependency installs, or Git pushes were performed.\n'
