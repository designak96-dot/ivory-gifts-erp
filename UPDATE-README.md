# Ivory Gifts ERP — Expense Attachments + Cash Reconciliation — 2026-08-31-v46

## 1. Expense Attachments
Two genuinely separate upload fields in Finance → Expenses:
- **Expense Invoice / Bill** — optional
- **Payment Proof / Slip** — required (unchanged from before — this was
  already required)

Both accept JPG/JPEG/PNG/WEBP/PDF, support drag-and-drop and click to
browse, and are stored at completely independent paths — uploading one
never touches or overwrites the other. Each is viewable and downloadable
separately from the expense register. Historical expenses (posted before
this update) are completely unaffected — their existing proof data is
untouched, and they simply show "Not provided" for the new invoice
field they never had.

**A real pre-existing bug was found and fixed along the way**: the route
for viewing an expense's proof required `expenses.manage`, but the
controller itself was written to also allow `expenses.view` — silently
blocking view-only staff who should have had access. Fixed at the route
level, with a regression test guarding it.

## 2. Cash Reconciliation
New page: **Finance → Cash Reconciliation**.

Automatically computes **Opening Cash + Cash In − Cash Out = Expected
Cash Balance** directly from the real double-entry ledger — the same
source of truth every other financial figure in this app already comes
from, not a separate, parallel calculation that could drift out of
sync. This covers cash customer payments and cash expenses (already
posted to the ledger by existing features) plus supplier cash payments,
cash refunds, petty cash, and approved adjustments, all recorded through
one new Adjustment form.

At reconciliation time, enter the Reconciliation Date, Cash Account, and
Actual Physical Cash Count. The system shows Expected Cash, Physical
Cash, and the Difference — with **"Cash difference requires review."**
displayed whenever they don't match. The reconciliation never
automatically changes any existing transaction — it only ever records a
new snapshot.

Owner/Admin can record an adjustment (Amount, Cash In/Out, Reason, Date,
optional proof), each one auto-numbered **CR-xxxxx** (Cash Receipt) or
**CP-xxxxx** (Cash Payment) and posted to the ledger through a dedicated
clearing account, so it genuinely affects future reconciliations rather
than just being a note. Full audit trail via creator and timestamps on
every record.

**A real bug was caught and fixed during development**: an early version
of the calculation filtered ledger lines by `status='posted'`, which
would have excluded a reversed transaction's original lines while still
counting its reversal — leaving a phantom balance that shouldn't exist.
Caught by a dedicated test before it shipped; the existing, proven
pattern from `FinancialSummaryService` (no status filter — both sides of
a reversal naturally cancel) was used instead.

## Testing
**484/484 automated tests passing**, including 12 new tests specifically
covering the reconciliation math (opening balance across month
boundaries, the reversal-cancellation guard), CR-/CP- reference
generation, adjustment-to-reconciliation integration (verified an
adjustment genuinely moves the next computed expected balance, not just
gets recorded), and permission boundaries. Verified end-to-end with a
real rendered page showing correct live numbers (Opening 2,000 + Cash In
800 − Cash Out 350 = Expected 2,450, correctly flagged against a 2,400
physical count as a real difference). Fresh-install migration confirmed
clean.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260831-v46.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Does not reset the database, run migrate:fresh, touch .env, or
regenerate APP_KEY. All new database changes are additive; existing
expense, payment, and accounting records are completely untouched.
