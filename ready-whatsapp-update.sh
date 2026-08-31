#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-}"
MAINTENANCE=0
BACKUP_DIR="$APP_DIR/storage/app/private/backups/ready-whatsapp-$(date +%Y%m%d-%H%M%S)"

red='\033[0;31m'; green='\033[0;32m'; amber='\033[0;33m'; reset='\033[0m'
info(){ printf "${amber}==>${reset} %s\n" "$*"; }
fail(){ printf "${red}HOTFIX FAILED:${reset} %s\n" "$*" >&2; exit 1; }

restore_backup(){
  if [[ -d "$BACKUP_DIR/files" ]]; then
    while IFS= read -r rel; do
      [[ -z "$rel" ]] && continue
      if [[ -f "$BACKUP_DIR/files/$rel" ]]; then
        mkdir -p "$APP_DIR/$(dirname "$rel")"
        cp -f "$BACKUP_DIR/files/$rel" "$APP_DIR/$rel"
      fi
    done < "$BACKUP_DIR/manifest.txt" 2>/dev/null || true
  fi
}

cleanup(){
  if [[ "$MAINTENANCE" == 1 && -n "$PHP_BIN" && -x "$PHP_BIN" ]]; then
    "$PHP_BIN" "$APP_DIR/artisan" up >/dev/null 2>&1 || true
  fi
}

on_error(){
  printf "\n${red}Ready WhatsApp update stopped. Restoring changed source files from backup.${reset}\n" >&2
  restore_backup
  cleanup
}
trap on_error ERR
trap cleanup EXIT

info "Ivory Gifts ERP — Ready WhatsApp emoji hotfix"

if [[ -z "$PHP_BIN" ]]; then
  for candidate in "$(command -v php 2>/dev/null || true)" /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php; do
    if [[ -n "$candidate" && -x "$candidate" ]]; then PHP_BIN="$candidate"; break; fi
  done
fi
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail "PHP CLI not found. Run with PHP_BIN=/path/to/php84-or-php85."
[[ -f "$APP_DIR/artisan" ]] || fail "Run this from the existing ivory-accounts application directory."
[[ -f "$APP_DIR/patches/apply-ready-whatsapp-hotfix.php" ]] || fail "Patch helper is missing. Re-extract the ZIP."
[[ -f "$APP_DIR/resources/views/partials/_ready-whatsapp-js.blade.php" ]] || fail "Ready WhatsApp browser partial is missing. Re-extract the ZIP."

TARGETS=(
  "app/Http/Controllers/WhatsAppShareController.php"
  "resources/views/layouts/app.blade.php"
  "resources/views/deliveries/_live.blade.php"
  "resources/views/deliveries/show.blade.php"
  "resources/views/orders/index.blade.php"
  "resources/views/orders/show.blade.php"
)

mkdir -p "$BACKUP_DIR/files"
: > "$BACKUP_DIR/manifest.txt"
for rel in "${TARGETS[@]}"; do
  [[ -f "$APP_DIR/$rel" ]] || fail "Missing expected application file: $rel"
  mkdir -p "$BACKUP_DIR/files/$(dirname "$rel")"
  cp -f "$APP_DIR/$rel" "$BACKUP_DIR/files/$rel"
  printf '%s\n' "$rel" >> "$BACKUP_DIR/manifest.txt"
done
info "Backup created: $BACKUP_DIR"

"$PHP_BIN" "$APP_DIR/artisan" down --retry=15 --secret="ready-whatsapp-update" >/dev/null 2>&1 || true
MAINTENANCE=1

"$PHP_BIN" "$APP_DIR/patches/apply-ready-whatsapp-hotfix.php" "$APP_DIR"
"$PHP_BIN" -l "$APP_DIR/app/Http/Controllers/WhatsAppShareController.php" >/dev/null

grep -q 'String.fromCodePoint(0x1F44B)' "$APP_DIR/resources/views/partials/_ready-whatsapp-js.blade.php" || fail "Wave code point check failed."
grep -q 'String.fromCodePoint(0x1F90D)' "$APP_DIR/resources/views/partials/_ready-whatsapp-js.blade.php" || fail "Heart code point check failed."
grep -q "@include('partials._ready-whatsapp-js')" "$APP_DIR/resources/views/layouts/app.blade.php" || fail "Browser handler include check failed."

"$PHP_BIN" "$APP_DIR/artisan" view:clear >/dev/null
"$PHP_BIN" "$APP_DIR/artisan" up >/dev/null
MAINTENANCE=0

echo
echo -e "${green}Ready WhatsApp update complete.${reset}"
echo "Backup kept at: $BACKUP_DIR"
