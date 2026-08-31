Ivory Gifts ERP — Ready WhatsApp emoji hotfix
Base: GitHub main commit 2515d2937e9894a1ddf7e97d3fac1194679c90ea

1) Upload this ZIP to the existing ~/ivory-accounts directory.
2) Extract it into ~/ivory-accounts (merge/overwrite package files).
3) Run:

   cd ~/ivory-accounts
   PHP_BIN=/opt/alt/php85/usr/bin/php bash ready-whatsapp-update.sh

This update has no database migration and changes only the Ready WhatsApp message flow.
Delivered WhatsApp message continues through the existing handler unchanged.
A source-file backup is created automatically under storage/app/private/backups/.
