#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PAYLOAD_DIR="$APP_DIR/theme-payload"
SOURCE_TARGET="$APP_DIR/resources/css/app.css"
BUILD_TARGET="$APP_DIR/public/build/assets/app.css"
CPANEL_ACCOUNTS_DIR="$(dirname "$APP_DIR")/public_html/accounts"
BRIDGE_TARGET="$CPANEL_ACCOUNTS_DIR/build/assets/app.css"
BACKUP_DIR="$APP_DIR/storage/app/private/backups/theme-$(date +%Y%m%d-%H%M%S)"

SOURCE_PAYLOAD="$PAYLOAD_DIR/resources/css/app.css"
BUILD_PAYLOAD="$PAYLOAD_DIR/public/build/assets/app.css"

[[ -f "$APP_DIR/artisan" ]] || { echo "Run this from the Ivory Gifts ERP application root." >&2; exit 1; }
[[ -f "$SOURCE_PAYLOAD" ]] || { echo "Missing theme source payload." >&2; exit 1; }
[[ -f "$BUILD_PAYLOAD" ]] || { echo "Missing compiled theme payload." >&2; exit 1; }

mkdir -p "$BACKUP_DIR/resources/css" "$BACKUP_DIR/public/build/assets"
[[ -f "$SOURCE_TARGET" ]] && cp "$SOURCE_TARGET" "$BACKUP_DIR/resources/css/app.css"
[[ -f "$BUILD_TARGET" ]] && cp "$BUILD_TARGET" "$BACKUP_DIR/public/build/assets/app.css"

mkdir -p "$(dirname "$SOURCE_TARGET")" "$(dirname "$BUILD_TARGET")"
cp "$SOURCE_PAYLOAD" "$SOURCE_TARGET"
cp "$BUILD_PAYLOAD" "$BUILD_TARGET"
chmod 0644 "$SOURCE_TARGET" "$BUILD_TARGET"

if [[ -d "$CPANEL_ACCOUNTS_DIR" ]]; then
    mkdir -p "$BACKUP_DIR/public_html/accounts/build/assets" "$(dirname "$BRIDGE_TARGET")"
    [[ -f "$BRIDGE_TARGET" ]] && cp "$BRIDGE_TARGET" "$BACKUP_DIR/public_html/accounts/build/assets/app.css"
    cp "$BUILD_PAYLOAD" "$BRIDGE_TARGET"
    chmod 0644 "$BRIDGE_TARGET"
fi

echo "Ivory Gifts colorful liquid-glass theme installed."
echo "Dark mode and light mode are included."
echo "Backup: $BACKUP_DIR"
echo "No PHP, database, Blade, JavaScript, route, automation, or ERP feature file was changed."
