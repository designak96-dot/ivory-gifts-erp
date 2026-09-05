# Ivory Gifts ERP — Imported Orders Now Actually Post to Accounting — 2026-09-05-v65

## Your question surfaced a real, important gap

You asked: if you import Current or Historical Orders, does it come to
Income? I checked the actual code rather than assume, and the honest
answer at the time was **no**.

Here's exactly why. When you create an order and invoice the normal
way (through the app's own screens), it posts a real journal entry:
Debit Accounts Receivable, Credit Sales Revenue (account 4000), Credit
VAT Output — and when a payment comes in, a separate entry: Debit
Cash/Bank, Credit Accounts Receivable. That's what actually makes
revenue show up in Income/P&L reports and cash show up in Bank/Cash
Reconciliation.

The import service was only ever creating the `Invoice` **record** —
correct-looking in the Invoices list, correctly tracking paid/remaining
— but never actually posting either of those journal entries. So
imported revenue would have been genuinely invisible in your P&L,
Income reports, and Cashflow, even though the invoice itself looked
completely normal.

## Fixed

Both **Historical Orders** and **Current Orders** imports now post the
exact same journal entries the manual flow does:
- Revenue is posted at invoice creation (Debit AR, Credit Sales
  Revenue, Credit VAT Output) — this happens even for an unpaid order,
  which is correct accrual-basis accounting.
- If the order shows an amount paid, a **real Payment record** is
  created (not just a number on the Invoice) and its own journal entry
  posted (Debit Cash/Bank, Credit AR) — so it now genuinely appears in
  Bank/Cash Reconciliation and Cashflow too.

**Re-importing the same order is still safe** — verified directly that
running the same import twice does not create a second revenue entry
or a second payment. An unpaid order correctly posts revenue but never
invents a payment that didn't happen.

## Testing
**141/141 of my new and touched tests passing** — 4 new this round,
each checking the actual `journal_entries`/`journal_lines` tables
directly (not just that the Invoice record looks right): revenue
correctly hits account 4000, AR correctly hits 1100, a real Payment
gets created and posted when paid, re-import doesn't double-post, and
an unpaid order posts revenue without inventing a payment. All 22
pre-existing import tests re-verified passing, and the full suite shows
zero new failures beyond the same 11 pre-existing, unrelated ones from
every prior update.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260905-v65.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

## Important — about orders you already imported before this fix

This fix changes behavior for **new imports going forward**. Orders you
already imported before installing this update will **not**
retroactively post to accounting just from installing this ZIP — their
Invoice records already exist, so the "don't double-post" safety check
will correctly leave them alone. If you have already-imported orders
whose revenue you need reflected in accounting, tell me and I'll build
a one-time, safe catch-up command that posts the missing entries for
exactly those orders — without touching anything already posted
correctly.

Does not reset the database, run migrate:fresh, touch .env, or
regenerate APP_KEY.
