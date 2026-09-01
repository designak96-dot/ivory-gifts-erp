# Ivory Gifts ERP — Finance: Account Transfer — 2026-09-01-v52

## What's new

**Finance → Account Transfer.** Move money between Cash, Bank, and Petty
Cash accounts (e.g. Cash → Bank deposit AED 1,500) with From Account, To
Account, Amount, Date, Reference, Notes, and an optional proof/deposit
slip upload.

On save it posts a single balanced journal entry — credit the From
account, debit the To account — through the existing double-entry
ledger. Because both sides are always asset accounts, this can never
create income, never create an expense, and never affects profit; it
only relocates funds already on the books. Same-account transfers are
rejected. Every transfer gets its own number (`TRF-2026-00001`, ...)
and a full audit trail (create/update/delete) via the standard
AuditLog mechanism already used elsewhere in the app.

**Cash Reconciliation** picks up every transfer automatically — no
separate calculation, it reads the same ledger lines it always has.

**Bank Reconciliation matching** was extended so a transfer that
touches a bank account is recognized on the statement (by reference,
then by amount+date) instead of showing up as an unmatched customer
payment or supplier expense, which is what would otherwise happen to a
Cash→Bank deposit or Bank→Cash withdrawal line on a real bank
statement.

## Testing

This environment has no `composer`/package-registry access, so the
project's PHPUnit suite could not be executed here — I want to be
upfront about that rather than claim a test count I didn't produce.
What I did verify:

- `php -l` on every changed/new PHP file — no syntax errors.
- Full cross-check of every DB column referenced in code against the
  new migration's schema, every route name for collisions, and the
  route-model-binding parameter names against controller signatures.
- A standalone PHP simulation of the posting logic itself (not the
  Laravel app, just the arithmetic): confirms `Cash −1,500 / Bank
  +1,500`, confirms the entry is balanced, confirms no income/expense
  account code ever appears in the posted lines, and confirms a
  zero-amount entry is rejected.
- Manual read-through of `git diff` for all 13 files — confirmed
  every change is additive (new `if` branches, new array keys, one new
  route/nav line each) with no existing line removed or altered, and
  that the Ready/Delivered WhatsApp implementation is untouched.

Please do one real Cash→Bank transfer on staging (or during a low-
traffic window) before relying on this in production.

## Install

```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260901-v52.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Does not reset the database, run `migrate:fresh`, touch `.env`, or
regenerate `APP_KEY`. Two new, additive migrations only (new
`account_transfers` table, plus the `TRF-` numbering sequence used by
existing installs that don't get a fresh seed run). No unrelated ERP
feature touched — only Finance/Account Transfer, Bank Reconciliation
matching, and the sidebar nav link.
