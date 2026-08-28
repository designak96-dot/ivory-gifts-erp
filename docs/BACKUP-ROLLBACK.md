# Backup, upgrade and rollback

## Backups

- `php artisan ivory:backup --type=manual` creates a MySQL snapshot under `storage/app/private/backups`, outside the web root.
- `deploy.sh` refuses to migrate an existing installation unless it can create a pre-deployment backup first.
- The scheduler creates a daily backup at 02:15 and retains the latest configured number (`BACKUP_RETENTION_COUNT`, default 30).
- `deploy.sh` generates `BACKUP_ENCRYPTION_KEY` when it is blank. Backups use AES-256-CBC/PBKDF2. Preserve the final `.env` securely; a lost key cannot be recovered.
- Copy backups off the cPanel account regularly. A backup on the same server is not sufficient disaster recovery.

## Safe code rollback

1. Put the application into maintenance mode: `php artisan down --retry=60`.
2. Preserve the current application folder by renaming it to a timestamped release directory. Do not delete it.
3. Restore the previous application release at `/home/CPANEL_USER/ivory-accounts` and its matching `.env`.
4. Run `php artisan migrate:rollback --step=1 --force` only when the reverted release documents that its latest migration is reversible and no later production data depends on it.
5. Run `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Run `bash verify-install.sh`, then `php artisan up`.

Never use `migrate:fresh`, `db:wipe`, direct table deletion, or overwrite a production database during rollback.

## Database restore

Always restore first into a new scratch database. Verify row counts and key reports, then schedule a maintenance window. For an unencrypted backup: `gunzip -c backup.sql.gz | mysql -u USER -p DATABASE`. For an encrypted backup, decrypt a copy first with the same OpenSSL parameters and key. Never pipe an unverified backup directly over production.
