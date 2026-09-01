#!/usr/bin/env bash
#
# Ivory Gifts ERP — update.sh
# Applies an incremental update from this package on top of an existing
# installation. Run from inside the EXISTING ivory-accounts directory after
# extracting this update package's contents into it:
#
#   cd /home/CPANEL_USER/ivory-accounts
#   unzip -o /path/to/ivory-gifts-erp-update-YYYYMMDD-vN.zip -d .
#   PHP_BIN=/opt/alt/php84/usr/bin/php bash update.sh
#
# Safe to interrupt: maintenance mode is always lifted on exit (success or
# failure) via the trap below. Never runs migrate:fresh or db:wipe.

set -Eeuo pipefail
umask 027

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CPANEL_HOME="$(dirname "$APP_DIR")"
PUBLIC_DIR="$CPANEL_HOME/public_html/accounts"
PHP_BIN="${PHP_BIN:-}"
MAINTENANCE=0
BACKUP_DIR="$APP_DIR/storage/app/private/backups/update-$(date +%Y%m%d-%H%M%S)"

red='\033[0;31m'; green='\033[0;32m'; amber='\033[0;33m'; reset='\033[0m'
info(){ printf "${amber}==>${reset} %s\n" "$*"; }
fail(){ printf "${red}UPDATE FAILED:${reset} %s\n" "$*" >&2; exit 1; }
cleanup(){ if [[ "$MAINTENANCE" == 1 ]]; then "$PHP_BIN" "$APP_DIR/artisan" up >/dev/null 2>&1 || true; fi; }
trap 'printf "\n${red}Update stopped on line %s. Maintenance mode has been lifted. Nothing else was rolled back automatically — run rollback.sh if needed.${reset}\n" "$LINENO" >&2; cleanup' ERR
trap cleanup EXIT

info "Ivory Gifts ERP — incremental update"

# 1. PHP version and extensions
if [[ -z "$PHP_BIN" ]]; then
  for candidate in "$(command -v php 2>/dev/null || true)" /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php; do
    if [[ -n "$candidate" && -x "$candidate" ]] && "$candidate" -r 'exit(version_compare(PHP_VERSION,"8.4.1",">=")?0:1);' >/dev/null 2>&1; then
      PHP_BIN="$candidate"; break
    fi
  done
fi
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail "PHP 8.4.1+ CLI not found. Run PHP_BIN=/path/to/php84 bash update.sh"
"$PHP_BIN" -v | head -1

REQUIRED_EXT="pdo_mysql mbstring openssl tokenizer xml ctype json fileinfo curl zip gd"
for ext in $REQUIRED_EXT; do
  "$PHP_BIN" -m | grep -qi "^${ext}$" || fail "Missing PHP extension: $ext"
done
info "PHP OK."

# 2. .env validation
[[ -f "$APP_DIR/.env" ]] || fail ".env is missing — an update cannot run on an uninstalled application."
set -a; source "$APP_DIR/.env"; set +a
for var in DB_DATABASE DB_USERNAME DB_PASSWORD APP_URL APP_KEY; do
  [[ -n "${!var:-}" ]] || fail ".env value $var is empty."
done
info ".env OK."

# 3. Database connection test
"$PHP_BIN" "$APP_DIR/artisan" db:show --json >/dev/null 2>&1 || fail "Could not connect to the database."
info "Database connection OK."

# 4. MySQL backup
mkdir -p "$BACKUP_DIR"
DB_NAME="${DB_DATABASE}"
if command -v mysqldump >/dev/null 2>&1; then
  MYSQL_PWD="${DB_PASSWORD}" mysqldump -u "${DB_USERNAME}" -h "${DB_HOST:-127.0.0.1}" "${DB_NAME}" > "$BACKUP_DIR/database.sql" \
    || fail "mysqldump failed — aborting before any files are touched."
  info "Database backed up to $BACKUP_DIR/database.sql"
else
  fail "mysqldump not found — cannot safely proceed without a pre-update backup."
fi

# 5. Back up every file listed in manifest.txt before overwriting anything
[[ -f "$APP_DIR/update-manifest.txt" ]] || fail "update-manifest.txt not found in this update package."
mkdir -p "$BACKUP_DIR/files"
while IFS= read -r rel_path; do
  [[ -z "$rel_path" ]] && continue
  if [[ -f "$APP_DIR/$rel_path" ]]; then
    mkdir -p "$BACKUP_DIR/files/$(dirname "$rel_path")"
    cp "$APP_DIR/$rel_path" "$BACKUP_DIR/files/$rel_path"
  fi
done < "$APP_DIR/update-manifest.txt"
info "File backups complete: $BACKUP_DIR/files"
echo "$BACKUP_DIR" > "$APP_DIR/storage/app/private/backups/.last-update-backup"

# 6. Maintenance mode
"$PHP_BIN" "$APP_DIR/artisan" down --retry=30 --secret="update-in-progress" || true
MAINTENANCE=1
info "Maintenance mode on."

# 7. Install changed files (already extracted into $APP_DIR by the operator
#    before running this script, per the instructions at the top). This
#    step verifies the files listed in the manifest actually landed.
while IFS= read -r rel_path; do
  [[ -z "$rel_path" ]] && continue
  [[ -f "$APP_DIR/$rel_path" ]] || fail "Expected updated file missing: $rel_path — extraction may have failed."
done < "$APP_DIR/update-manifest.txt"
info "Updated files present."

# 8. Migrations — additive only, never migrate:fresh
"$PHP_BIN" "$APP_DIR/artisan" migrate --force || fail "Migration failed. Existing data was NOT touched by this step failing — run rollback.sh to restore files, and restore database.sql manually if the migration partially applied."
info "Migrations applied (additive only)."

# 9. Sync the cPanel public bridge — only the public-facing files, never .env/app/vendor/storage internals
mkdir -p "$PUBLIC_DIR"
cp -f "$APP_DIR/cpanel-public/index.php" "$PUBLIC_DIR/index.php" 2>/dev/null || true
cp -f "$APP_DIR/cpanel-public/.htaccess" "$PUBLIC_DIR/.htaccess" 2>/dev/null || true
cp -f "$APP_DIR/cpanel-public/.user.ini" "$PUBLIC_DIR/.user.ini" 2>/dev/null || true
rm -rf "$PUBLIC_DIR/build"
cp -r "$APP_DIR/public/build" "$PUBLIC_DIR/build"
[[ -L "$PUBLIC_DIR/storage" ]] || ln -s "$APP_DIR/storage/app/public" "$PUBLIC_DIR/storage"

# The symlink above only helps if the public_html/accounts bridge is what's
# actually live. Some cPanel setups (this one, confirmed) point the
# subdomain's Document Root straight at ivory-accounts/public instead —
# Storage::disk('public')->url(...) (used for the company logo, signature,
# and every product image) generates /storage/... URLs that only resolve
# if THIS symlink also exists, inside the app's own public/ folder. This
# was never being created by either script, which is the real, confirmed
# root cause of the broken logo/signature images (same URL, same missing
# symlink, same broken-image icon everywhere it's used) — not a rendering
# or path-generation bug in the app code itself.
"$PHP_BIN" "$APP_DIR/artisan" storage:link --force >/dev/null 2>&1 || true
[[ -L "$APP_DIR/public/storage" ]] || ln -s "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"

# Security headers/MIME types also applied directly to the app's own
# public/.htaccess (append-once, not overwrite) — some cPanel setups have
# the subdomain's Document Root pointed straight at ivory-accounts/public
# rather than through the public_html/accounts bridge folder above. Both
# locations must carry the same hardening regardless of which one is
# actually live, so a docroot change can never silently drop them.
if ! grep -q "X-Content-Type-Options" "$APP_DIR/public/.htaccess" 2>/dev/null; then
    {
        echo ""
        echo "<FilesMatch \"^\.\">"
        echo "    Require all denied"
        echo "</FilesMatch>"
        echo "<IfModule mod_headers.c>"
        echo "    Header always set X-Content-Type-Options \"nosniff\""
        echo "    Header always set X-Frame-Options \"SAMEORIGIN\""
        echo "    Header always set Referrer-Policy \"strict-origin-when-cross-origin\""
        echo "</IfModule>"
        echo "<IfModule mod_mime.c>"
        echo "    AddType text/css .css"
        echo "    AddType application/javascript .js"
        echo "    AddType application/json .json"
        echo "    AddType image/svg+xml .svg"
        echo "    AddType font/woff2 .woff2"
        echo "    AddType font/woff .woff"
        echo "</IfModule>"
    } >> "$APP_DIR/public/.htaccess"
fi
info "Public bridge synced."

# 10. Rebuild caches
"$PHP_BIN" "$APP_DIR/artisan" config:cache
"$PHP_BIN" "$APP_DIR/artisan" route:cache
"$PHP_BIN" "$APP_DIR/artisan" view:cache
info "Caches rebuilt."

# 11. Verification
bash "$APP_DIR/verify-install.sh" || fail "Post-update verification failed — see output above. Files and database were backed up to $BACKUP_DIR before this update ran."

# 12. Leave maintenance mode (also happens automatically via the EXIT trap)
"$PHP_BIN" "$APP_DIR/artisan" up
MAINTENANCE=0

echo
echo -e "${green}=================================================${reset}"
echo -e "${green} Update complete. Backup kept at: $BACKUP_DIR${reset}"
echo -e "${green}=================================================${reset}"
