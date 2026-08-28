#!/usr/bin/env bash
#
# Ivory Gifts ERP — rollback.sh
# Restores files backed up by the most recent update.sh run. Run from
# inside ivory-accounts:
#
#   cd /home/CPANEL_USER/ivory-accounts
#   PHP_BIN=/opt/alt/php84/usr/bin/php bash rollback.sh
#
# ============================================================================
# DATABASE ROLLBACK LIMITATION — READ THIS BEFORE RUNNING
# ============================================================================
# This script restores APPLICATION FILES ONLY (app/, resources/, routes/,
# config/, public bridge, compiled assets) from the pre-update backup taken
# by update.sh. It does NOT automatically restore the database.
#
# Why: migrations run by update.sh are additive (new tables/columns), and by
# the time you notice a problem, real new business data (a new customer, a
# new order) may already exist in the post-update schema. Blindly restoring
# database.sql would silently DELETE that new data. That decision has to be
# a deliberate, informed choice by you, not an automatic one by this script.
#
# If you are certain no real data was created since the update ran, restore
# the database backup manually:
#   mysql -u DB_USERNAME -p DB_DATABASE < <backup-dir>/database.sql
#
# If real data WAS created since the update, do not blanket-restore — export
# the new records first, or restore to a separate database and reconcile
# manually. This is a genuine limitation of any additive-migration rollback
# strategy, not a bug — surfacing it clearly here rather than pretending an
# automatic database rollback is always safe.
# ============================================================================

set -Eeuo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"

red='\033[0;31m'; green='\033[0;32m'; amber='\033[0;33m'; reset='\033[0m'
fail(){ printf "${red}ROLLBACK FAILED:${reset} %s\n" "$*" >&2; exit 1; }

BACKUP_POINTER="$APP_DIR/storage/app/private/backups/.last-update-backup"
[[ -f "$BACKUP_POINTER" ]] || fail "No record of a previous update backup was found. Nothing to roll back automatically."
BACKUP_DIR="$(cat "$BACKUP_POINTER")"
[[ -d "$BACKUP_DIR/files" ]] || fail "Backup directory $BACKUP_DIR/files does not exist."

printf "${amber}This will restore application files from: %s${reset}\n" "$BACKUP_DIR"
printf "This does NOT touch the database — see the notice at the top of this script.\n"
read -r -p "Type 'restore' to continue: " confirm
[[ "$confirm" == "restore" ]] || { echo "Cancelled."; exit 0; }

"$PHP_BIN" "$APP_DIR/artisan" down --retry=30 --secret="rollback-in-progress" || true

cp -r "$BACKUP_DIR/files/." "$APP_DIR/"
echo -e "${green}Files restored from $BACKUP_DIR/files${reset}"

"$PHP_BIN" "$APP_DIR/artisan" config:clear
"$PHP_BIN" "$APP_DIR/artisan" route:clear
"$PHP_BIN" "$APP_DIR/artisan" view:clear
"$PHP_BIN" "$APP_DIR/artisan" config:cache
"$PHP_BIN" "$APP_DIR/artisan" route:cache
"$PHP_BIN" "$APP_DIR/artisan" view:cache

"$PHP_BIN" "$APP_DIR/artisan" up

echo
echo -e "${green}File rollback complete.${reset}"
echo -e "${amber}Reminder: the database was NOT rolled back. See the notice at the top of this script if you need to restore it.${reset}"
echo "Database backup, if you decide to use it: $BACKUP_DIR/database.sql"
