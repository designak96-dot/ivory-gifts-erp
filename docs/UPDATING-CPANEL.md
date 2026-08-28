# Safe cPanel updates

This ZIP is a complete fresh-install release. Future changes must be shipped as a separate versioned update ZIP; do not delete the production database and do not replace `.env` or `storage`.

Every future update package must contain:

- a manifest listing every added or replaced file;
- an update script that requires the existing `/home/CPANEL_USER/ivory-accounts/.env`;
- a pre-update MySQL backup and a timestamped backup of every file listed in the manifest;
- additive, reversible Laravel migrations only;
- compiled public assets and public-bridge synchronization;
- production cache rebuilding and `verify-install.sh` execution;
- a rollback script that restores the file backup and documents any database rollback limitations.

The update script must never run `migrate:fresh`, `db:wipe`, `DROP DATABASE`, or delete business records. It must stop on an incomplete `.env`, PHP below 8.4.1, missing PHP extensions, a failed database connection, or a failed backup.

Before every update:

1. Download a cPanel account backup and an ERP database backup.
2. Keep `.env`, `storage/app/public`, and `storage/app/private/backups` unchanged.
3. Upload the versioned update ZIP into `/home/CPANEL_USER/ivory-accounts` and follow that update's instructions.
4. Run the included updater once with PHP 8.4.
5. Verify the dashboard, one existing order, documents, delivery schedule, product photos, and the cron job.

Do not use a fresh-install ZIP as an in-place update package.
