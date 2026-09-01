#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CPANEL_HOME="$(dirname "$APP_DIR")"
PUBLIC_DIR="$CPANEL_HOME/public_html/accounts"
PHP_BIN="${PHP_BIN:-}"
MAINTENANCE=0

red='\033[0;31m'; green='\033[0;32m'; amber='\033[0;33m'; reset='\033[0m'
info(){ printf "${amber}==>${reset} %s\n" "$*"; }
fail(){ printf "${red}INSTALLATION FAILED:${reset} %s\n" "$*" >&2; exit 1; }
cleanup(){ if [[ "$MAINTENANCE" == 1 ]]; then "$PHP_BIN" "$APP_DIR/artisan" up >/dev/null 2>&1 || true; fi; }
trap 'printf "\n${red}Installation stopped on line %s.${reset}\n" "$LINENO" >&2; cleanup' ERR

[[ "$(basename "$APP_DIR")" == "ivory-accounts" ]] || fail "Extract this package exactly to $CPANEL_HOME/ivory-accounts"
[[ -f "$APP_DIR/.env" ]] || fail ".env is missing. Copy .env.example to .env and enter the database details."
[[ -f "$APP_DIR/vendor/autoload.php" ]] || fail "The vendor directory is incomplete. Upload the complete ZIP again."

if [[ -z "$PHP_BIN" ]]; then
  for candidate in "$(command -v php 2>/dev/null || true)" /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php; do
    if [[ -n "$candidate" && -x "$candidate" ]] && "$candidate" -r 'exit(version_compare(PHP_VERSION,"8.4.1",">=")?0:1);' >/dev/null 2>&1; then
      PHP_BIN="$candidate"
      break
    fi
  done
fi
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail "PHP CLI was not found. Select PHP 8.4+ in cPanel, or run PHP_BIN=/path/to/php bash deploy.sh"

PHP_VERSION="$($PHP_BIN -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r 'exit(version_compare($argv[1],"8.4.1",">=")?0:1);' "$PHP_VERSION" || fail "PHP 8.4.1 or newer is required by the bundled dependencies; found $PHP_VERSION"
info "PHP $PHP_VERSION"

required_ext=(ctype curl dom fileinfo filter hash mbstring openssl pcre pdo pdo_mysql session tokenizer xml gd)
missing=()
php_modules="$($PHP_BIN -m)"
for ext in "${required_ext[@]}"; do grep -qi "^${ext}$" <<<"$php_modules" || missing+=("$ext"); done
[[ ${#missing[@]} -eq 0 ]] || fail "Missing PHP extensions: ${missing[*]}"

env_value(){ sed -n "s/^${1}=//p" "$APP_DIR/.env" | tail -n 1 | sed -e "s/^[\"']//" -e "s/[\"']$//" -e 's/[[:space:]]*$//'; }
required_env=(APP_NAME APP_ENV APP_URL DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD)
for key in "${required_env[@]}"; do value="$(env_value "$key")"; [[ -n "$value" && "$value" != "null" ]] || fail "$key is incomplete in .env"; done
[[ "$(env_value APP_ENV)" == "production" ]] || fail "APP_ENV must be production"
[[ "$(env_value APP_DEBUG)" == "false" ]] || fail "APP_DEBUG must be false in production"
[[ "$(env_value DB_CONNECTION)" == "mysql" ]] || fail "DB_CONNECTION must be mysql on cPanel"
[[ "$(env_value APP_URL)" == "https://accounts.ivorygifts.ae" ]] || fail "APP_URL must be https://accounts.ivorygifts.ae"

mkdir -p "$APP_DIR/storage/framework/cache/data" "$APP_DIR/storage/framework/sessions" "$APP_DIR/storage/framework/views" "$APP_DIR/storage/logs" "$APP_DIR/storage/app/public" "$APP_DIR/storage/app/private/backups" "$APP_DIR/bootstrap/cache"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 0775 {} +
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0664 {} +

"$PHP_BIN" "$APP_DIR/artisan" config:clear --no-interaction >/dev/null
if [[ -z "$(env_value APP_KEY)" ]]; then info "Generating application key"; "$PHP_BIN" "$APP_DIR/artisan" key:generate --force --no-interaction; fi
if [[ -z "$(env_value BACKUP_ENCRYPTION_KEY)" ]]; then
  backup_key="$($PHP_BIN -r 'echo bin2hex(random_bytes(32));')"
  sed -i "s/^BACKUP_ENCRYPTION_KEY=.*/BACKUP_ENCRYPTION_KEY=${backup_key}/" "$APP_DIR/.env"
  info "Generated the encrypted-backup key"
fi
info "Testing database connection"; "$PHP_BIN" "$APP_DIR/artisan" ivory:db-test --no-interaction

if "$PHP_BIN" "$APP_DIR/artisan" migrate:status --no-ansi 2>/dev/null | grep '2026_01_01_000400_create_operations_accounting_tables' | grep -q 'Ran'; then
  info "Creating required pre-deployment database backup"
  command -v mysqldump >/dev/null 2>&1 || fail "mysqldump is required before updating an existing installation"
  "$PHP_BIN" "$APP_DIR/artisan" ivory:backup --type=pre-deploy --no-interaction
  "$PHP_BIN" "$APP_DIR/artisan" down --retry=60 --refresh=15 >/dev/null
  MAINTENANCE=1
fi

info "Running safe versioned migrations"; "$PHP_BIN" "$APP_DIR/artisan" migrate --force --no-interaction
info "Seeding required system data"; "$PHP_BIN" "$APP_DIR/artisan" db:seed --class="Database\\Seeders\\SystemDataSeeder" --force --no-interaction

info "Installing the protected cPanel public bridge"
mkdir -p "$PUBLIC_DIR"
install -m 0644 "$APP_DIR/cpanel-public/index.php" "$PUBLIC_DIR/index.php"
install -m 0644 "$APP_DIR/cpanel-public/.htaccess" "$PUBLIC_DIR/.htaccess"
install -m 0644 "$APP_DIR/cpanel-public/robots.txt" "$PUBLIC_DIR/robots.txt"
install -m 0644 "$APP_DIR/cpanel-public/.user.ini" "$PUBLIC_DIR/.user.ini"
mkdir -p "$PUBLIC_DIR/build"
cp -R "$APP_DIR/public/build/." "$PUBLIC_DIR/build/"
find "$PUBLIC_DIR/build" -type d -exec chmod 0755 {} +
find "$PUBLIC_DIR/build" -type f -exec chmod 0644 {} +
if [[ -L "$PUBLIC_DIR/storage" ]]; then
  [[ "$(readlink "$PUBLIC_DIR/storage")" == "$APP_DIR/storage/app/public" ]] || fail "$PUBLIC_DIR/storage points to an unexpected target"
elif [[ -e "$PUBLIC_DIR/storage" ]]; then
  fail "$PUBLIC_DIR/storage exists and is not the expected symbolic link"
else
  ln -s "$APP_DIR/storage/app/public" "$PUBLIC_DIR/storage"
fi

# Same symlink, inside the app's OWN public/ folder — required whenever
# the subdomain's Document Root points straight at ivory-accounts/public
# rather than through the public_html/accounts bridge above. Without this,
# Storage::disk('public')->url(...) (company logo, signature, every
# product image) generates URLs that 404, showing as a broken image icon.
"$PHP_BIN" "$APP_DIR/artisan" storage:link --force >/dev/null 2>&1 || true
[[ -L "$APP_DIR/public/storage" ]] || ln -s "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"

# Security headers/MIME types also applied directly to the app's own
# public/.htaccess (append-once) — some cPanel setups point the subdomain's
# Document Root straight at ivory-accounts/public rather than through the
# public_html/accounts bridge above. Both locations must carry the same
# hardening regardless of which one ends up being the live one.
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

info "Caching production configuration"
"$PHP_BIN" "$APP_DIR/artisan" optimize:clear --no-interaction
"$PHP_BIN" "$APP_DIR/artisan" config:cache --no-interaction
"$PHP_BIN" "$APP_DIR/artisan" route:cache --no-interaction
"$PHP_BIN" "$APP_DIR/artisan" view:cache --no-interaction
info "Running installation verification"; PHP_BIN="$PHP_BIN" bash "$APP_DIR/verify-install.sh"

cleanup; MAINTENANCE=0; trap - ERR
printf "\n${green}IVORY GIFTS ERP INSTALLED SUCCESSFULLY${reset}\n"
printf "Open: %s\n" "$(env_value APP_URL)"
printf "Create the first Owner account in the browser.\n"
printf "Cron: * * * * * %s %s/artisan schedule:run >> /dev/null 2>&1\n" "$PHP_BIN" "$APP_DIR"
