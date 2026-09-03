# Ivory Gifts ERP — Reset All Trial Data, Keep Only Products — 2026-09-03-v57

## Important — a real discovery worth knowing about

You asked me to use the latest code from your GitHub repo, so I cloned
it directly and inspected it rather than assume. Your repo has its own
history I'd never seen before — files like `HOTFIX-README.md` and
`CLAUDE-CONTINUATION-PROMPT.md` — strong evidence a **separate AI
session** (most likely Claude Code, working directly against GitHub)
has been developing this same project independently of this chat. That
explains the Account Transfer feature and the theme change from
earlier — neither came from ChatGPT as assumed at the time; they came
from that other, separate lineage of work.

Given that, this update is built **directly on top of your actual
repository's code**, not my own separate copy — so it's consistent with
whatever that other session has already built (including its own
Account Transfer feature, which this doesn't touch or duplicate).

## What this adds

**Settings → Danger Zone** (owner role only, regardless of other
permissions someone might have): a single action that permanently
deletes every trial/transactional record —

- Customers, sales orders, quotations, invoices, payments, expenses
- The entire General Ledger (journal entries and lines) and Audit Log
- Deliveries, production jobs, raw materials and their purchases, suppliers
- Cash and Bank Reconciliation history, account transfers
- Import history, saved filters, tasks, sync logs, backups metadata

**Explicitly kept**: your Sales Products catalog, product categories,
tax rates, the chart of accounts structure (balances reset to zero,
not deleted), your user account and settings, numbering sequence
definitions (counters reset to zero, so new documents start at 1
again).

Requires typing the exact phrase `DELETE ALL DATA` — not a checkbox,
not a single click — since this is irreversible. A matching CLI command
(`php artisan ivory:reset-to-products-only`) is also available if you'd
rather run it from SSH.

## Two real bugs found and fixed before shipping
- My first attempt tried to log the reset into `audit_logs` itself, but
  that table's real schema is polymorphic (tied to a specific record,
  not a generic system event) — checked directly rather than guessed,
  and fixed by logging to Laravel's own log file instead.
- `products.supplier_id` would have been left pointing at a deleted
  supplier once suppliers are wiped — now explicitly cleared first, so
  the kept product catalog has no dangling references.

## Testing
**10 new tests, all passing**, run against your actual repository —
covering the full wipe (with every category of data verified gone and
products verified intact), the typed-confirmation safety gate, and
that a non-owner role with `settings.manage` still gets correctly
blocked. Confirmed this adds zero new failures to your repo's existing
test suite (which has 11 pre-existing failures, unrelated to this
change, from before I touched anything — worth knowing about
separately, but out of scope for today's request).

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260903-v57.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Does not reset the database, run migrate:fresh, touch .env, or
regenerate APP_KEY on its own — the actual data wipe only happens when
you explicitly trigger it from Settings or the CLI command afterward.
