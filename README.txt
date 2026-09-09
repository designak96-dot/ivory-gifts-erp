IVORY GIFTS - READY AND DELIVERED EMOJIS
Built from main 7c9ef64d6e7d6cc956341dbd044a34becf050c90, verified 8 Sep 2026.

Upload whatsapp-emoji-both.zip to /home/ivorygif/ivory-accounts, then:
cd /home/ivorygif/ivory-accounts
unzip -o whatsapp-emoji-both.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash install-whatsapp-emoji.sh

Hard refresh the ERP with Ctrl+F5. Both Ready and Delivered now use browser
codepoint emojis, bilingual wording, blank lines, bold, and one secure link.
Direct Web opening, same named window and Copy fallback are unchanged.
Delivered emoji addition is now expressly authorized by the user.
No theme, database, automation or phone-normalization changes.

Installer backs up layout, existing partial and existing CLAUDE.md under
storage/app/whatsapp-backups/<timestamp-random>/ before changing them.
It adds the WhatsApp include if absent without replacing your theme/layout.
It appends preservation guidance to CLAUDE.md without deleting existing text.
Rollback: restore those files from the printed backup folder and clear views.
If CLAUDE.md did not exist before, remove only the added marked section.

FUTURE CLAUDE UPDATES
The ZIP includes a contract and standalone regression test under
whatsapp-emoji-payload. Run from the ERP root or source checkout:
node whatsapp-emoji-payload/whatsapp-regression.mjs
This is a development check; Node is not needed for installation.

Important: this installer does not push to GitHub. Merge the fixed partial,
layout include, CLAUDE.md guidance and whatsapp-emoji-payload contract/test
into GitHub main before asking Claude to build future updates from main.
Attach this ZIP to Claude and say:
"First integrate the WhatsApp fix in this ZIP into the latest main. Preserve
both Ready and Delivered emojis and the direct Web single-window behavior.
Read CLAUDE.md and whatsapp-emoji-payload/PRESERVE-WHATSAPP.md. Run
node whatsapp-emoji-payload/whatsapp-regression.mjs before delivering updates.
Do not change or replace these features unless I explicitly ask."

Protection consists of source guidance and executable regression checks,
not a technical lock against arbitrary file overwrites. For enforcement,
run the test in CI and require it to pass before deployment.

VERIFICATION
Browser-mocked tests executed the actual handler for A/B/C in both statuses:
all 6 emojis survive one URL decode, no replacement characters, exact wording,
newlines, bold, one secure link, one named tab, invoice/proof gates and blocked
popup fallback. No live WhatsApp message sent. PHP installer not run locally.
WhatsApp Web must be signed in. Browsers cannot reliably discover unrelated
WhatsApp tabs or installed apps; reload/isolation can affect window reuse.
