Ivory Gifts Ready WhatsApp update — 8 September 2026
Base: designak96-dot/ivory-gifts-erp main
Commit: 7c9ef64d6e7d6cc956341dbd044a34becf050c90

Upload ivory-gifts-whatsapp-ready-20260908-cpanel.zip to the ERP root, then:

cd /home/ivorygif/ivory-accounts
unzip -o ivory-gifts-whatsapp-ready-20260908-cpanel.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash install-whatsapp-emoji.sh

Hard-refresh with Ctrl+F5. No update.sh, migration or frontend build required.

What this fixes
The main layout no longer includes the existing Ready Unicode script. The
installer adds its include and installs a Ready-only handler. It builds the
message from browser Unicode code points and directly opens WhatsApp Web
with a once-encoded draft, using the fixed name ivory_whatsapp. It does not
send the message. Desktop detection is not attempted by this update.

If a popup is blocked, use the Open WhatsApp button in the ERP modal.
If WhatsApp alters the draft, use Copy message and paste into WhatsApp.
The fallback copies the identical Unicode message used to create the URL.

Scope: existing layout receives one include; Ready script is replaced.
Delivered messages, server phone normalization, secure link generation,
CSS, other JavaScript bundles, data, automations and routes are untouched.
Existing separately opened WhatsApp tabs cannot be discovered by the ERP.
Tab reuse was tested with browser API mocks for A/B/C in the same ERP page;
browser cross-origin isolation and navigation can affect reuse in practice.

Verification: actual handler-generated URL text decoded correctly for three
customers, including Arabic and &/% characters. All six emojis, exact line
breaks, bold markers and one secure URL survive. Invoice/proof gates,
duplicate-handler protection, clipboard fallback and popup blocking checked.
No live customer message was sent. Installed-server behavior remains to be
confirmed after upload; this package is not an automatic GitHub push.

Backups: storage/app/whatsapp-backups/<timestamp>/
Restore its app.blade.php to resources/views/layouts/app.blade.php and
_ready-whatsapp-js.blade.php to resources/views/partials/, then view:clear.
