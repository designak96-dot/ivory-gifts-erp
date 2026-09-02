#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PAYLOAD="$ROOT/theme-payload"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/storage/app/theme-backups/exact-sites-$STAMP"

SOURCE_FILES=(
  "resources/css/app.css"
  "resources/static-assets/dashboard.css"
  "resources/static-assets/delivery.css"
  "resources/static-assets/order-entry.css"
  "resources/static-assets/sync.css"
)

BUILD_FILES=(
  "public/build/assets/app.css"
  "public/build/assets/dashboard.css"
  "public/build/assets/delivery.css"
  "public/build/assets/order-entry.css"
  "public/build/assets/sync.css"
)

if [[ ! -d "$ROOT/resources" || ! -d "$ROOT/public" ]]; then
  echo "Error: unzip and run this file from the Laravel ERP root directory."
  exit 1
fi

mkdir -p "$BACKUP"

for relative in "${SOURCE_FILES[@]}" "${BUILD_FILES[@]}"; do
  if [[ -f "$ROOT/$relative" ]]; then
    mkdir -p "$BACKUP/$(dirname "$relative")"
    cp -p "$ROOT/$relative" "$BACKUP/$relative"
  fi
done

for relative in "${SOURCE_FILES[@]}" "${BUILD_FILES[@]}"; do
  mkdir -p "$ROOT/$(dirname "$relative")"
  cp -p "$PAYLOAD/$relative" "$ROOT/$relative"
done

ACCOUNT_HOME="$(dirname "$ROOT")"
PUBLIC_ASSETS="$ACCOUNT_HOME/public_html/accounts/build/assets"
if [[ -d "$PUBLIC_ASSETS" ]]; then
  for file in app.css dashboard.css delivery.css order-entry.css sync.css; do
    if [[ -f "$PUBLIC_ASSETS/$file" ]]; then
      mkdir -p "$BACKUP/public-html-assets"
      cp -p "$PUBLIC_ASSETS/$file" "$BACKUP/public-html-assets/$file"
    fi
    cp -p "$PAYLOAD/public/build/assets/$file" "$PUBLIC_ASSETS/$file"
  done
  echo "Updated cPanel public assets: $PUBLIC_ASSETS"
fi

echo "Exact Sites liquid-glass theme installed successfully."
echo "Backup saved to: $BACKUP"
echo "No PHP, Blade, JavaScript, database, route, automation, or position was changed."
echo "Hard-refresh the browser with Ctrl+F5."
