# Ivory Gifts ERP — Customer Import Bug Fix — 2026-09-01-v52

## What this fixes

While building and testing real import templates for you, I found a
real bug in the customer import wizard: if you leave the optional
`source_id` column blank for more than one customer (the normal case —
most people migrating from Excel/WhatsApp records don't have "source
system IDs"), the second and every subsequent blank row would fail with
a database error, because empty text was being treated as a real value
that collided against a uniqueness rule meant for actual IDs — not as
"no ID provided."

Fixed by treating a blank `source_id` as genuinely blank. Caught this
by actually running your import templates through the real
upload → preview → dry-run → commit flow rather than assuming the
templates were correct, and verified the fix with dedicated tests.

## Testing
**522/522 automated tests passing**, including new tests that upload
each import template through the real app endpoints end-to-end and
confirm zero errors.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260901-v52.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Single-file fix. Does not reset the database, run migrate:fresh, touch
.env, or regenerate APP_KEY.
