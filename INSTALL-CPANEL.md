# Ivory Gifts ERP — cPanel installation

1. In cPanel, create a MySQL database and user, assign **ALL PRIVILEGES**, and note the full cPanel-prefixed database/user names.
2. Create `/home/CPANEL_USER/ivory-accounts` outside `public_html`.
3. Upload `ivory-gifts-erp-cpanel-ready.zip` into that directory and extract it there. The `artisan`, `app`, `vendor`, and `deploy.sh` items must be directly inside `ivory-accounts`.
4. Rename `.env.example` to `.env`. Enter `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Leave `APP_KEY` and `BACKUP_ENCRYPTION_KEY` blank; `deploy.sh` securely generates both.
5. In cPanel **Domains**, set `accounts.ivorygifts.ae` document root to `/home/CPANEL_USER/public_html/accounts`. Select PHP 8.4 and enable `pdo_mysql`, `mysqli`, `mbstring`, `curl`, `dom`, `fileinfo`, `openssl`, `xml`, and `zip`.
6. In SSH run:

   `cd /home/CPANEL_USER/ivory-accounts && PHP_BIN=/opt/alt/php84/usr/bin/php bash deploy.sh`

   If your host uses EasyApache instead of CloudLinux, use `/opt/cpanel/ea-php84/root/usr/bin/php`. Running plain `bash deploy.sh` also works when PHP 8.4 is already the default SSH version.

7. Open `https://accounts.ivorygifts.ae` and create the first Owner account. The setup route closes permanently afterward.

## Required cron

In cPanel → Cron Jobs, add this once, replacing both placeholders with the exact PHP path and cPanel username shown by `deploy.sh`:

`* * * * * /opt/alt/php84/usr/bin/php /home/CPANEL_USER/ivory-accounts/artisan schedule:run >> /dev/null 2>&1`

This single cron processes queued WooCommerce jobs without a permanent worker and runs scheduled backups without overlapping.

## Verify later

`cd /home/CPANEL_USER/ivory-accounts && bash verify-install.sh`

Never move the full application into `public_html`. Only `/public_html/accounts` is web-accessible.
