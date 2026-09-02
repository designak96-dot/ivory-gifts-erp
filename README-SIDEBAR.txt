Ivory Gifts ERP - Account Transfer sidebar link

The Account Transfer page already existed in GitHub main. This update only
adds its missing navigation link immediately below Bank & Cash in Finance.
Base commit: df17eae2577d10e0207959174b58f6ffa71f44b3

Upload ivory-account-transfer-sidebar.zip to /home/ivorygif/ivory-accounts.
Run in cPanel Terminal:

cd /home/ivorygif/ivory-accounts
unzip -o ivory-account-transfer-sidebar.zip && PHP_BIN=/opt/alt/php84/usr/bin/php bash install-sidebar-transfer.sh

Refresh the browser. The link is shown to users with accounting.view, which
is the same permission as Bank & Cash and the existing transfer page.
Recording a transfer still requires the existing accounting.manage permission.

The installer checks that the patch fits before editing, backs up the old
sidebar in storage/app/private, and clears compiled Blade views. It is safe
to run again. It does not overwrite .env or touch the database, financial
records, CSS, JavaScript, dependencies, or existing transfer logic.

This package has not been applied to your server or pushed to GitHub.
No Composer install, npm build, or deploy.sh run is needed for this update.

Rollback, if needed:
cd /home/ivorygif/ivory-accounts
git apply --reverse --check account-transfer-sidebar.patch && git apply --reverse account-transfer-sidebar.patch && /opt/alt/php84/usr/bin/php artisan view:clear

Validation: patch application and reverse-application checked against the
original main source. PHP/Laravel runtime tests cannot be run on the local
Windows workspace because PHP is not installed there.
