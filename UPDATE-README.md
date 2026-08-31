# Ivory Gifts ERP — Raw Material Purchase System — 2026-08-31-v48

## What's new: a simpler way to buy raw materials

**Raw Materials** — a genuinely separate entity from Sales Products,
with its own code, category, unit, stock, reorder level, preferred
supplier, and latest cost. Nothing about the existing `Products` table
or Sales module was touched.

**Direct Purchase Entry** — no Draft → Approved → Ordered → Received
workflow. One form: Supplier, Date, Quantity, Unit, Unit Price, VAT,
Payment Method, Payment Reference, Notes, Supplier Invoice/Bill. On
save, in one transaction: stock increases immediately, the material's
latest cost updates, and the correct accounting entry posts
automatically — Inventory (1200) debited at the real ex-VAT cost, Input
VAT (1300) posted separately, and Cash/Bank/Accounts Payable credited
depending on how it was paid. **No separate Expense entry is ever
created** — verified by a dedicated test that explicitly checks no
Expense record exists after a purchase.

**Cash payments** reduce the real Cash account and automatically feed
Cash Reconciliation, since that feature already reads from the same
ledger every purchase posts to — no separate integration needed, just
correct accounting.

**Bank payments** reduce the selected bank account and save the Payment
Reference. This is the part that genuinely required new work: Bank
Reconciliation's matching engine has been extended so raw material
purchases are now a real third source alongside Payments and Expenses —
verified end-to-end that a bank reference on a purchase gets correctly
matched, and confirmed the existing 15 bank reconciliation tests still
pass exactly as before.

**Unpaid purchases** create a real Supplier Payable — found and used the
`outstanding_payable` field that already existed on the Supplier model
for exactly this purpose, rather than adding a duplicate one.

**Price History** — Previous, Latest, Lowest, Highest, and % change,
computed live from actual purchase history (never a separate, driftable
table), plus a same-material price comparison across every supplier
who's sold it. Verified with a real 20% price-increase scenario end to
end, screenshotted against a live page.

## Scope respected
The existing formal Purchase Order system (Draft/Approved/Ordered/
Received) is completely untouched — still there for anyone using it.
This is a new, parallel, simpler path for everyday raw-material buying,
not a replacement of any existing code, table, or data. No Product,
Expense, or PurchaseOrder record was modified by this update.

## Testing
**516/516 automated tests passing**, including 13 new tests specific to
this feature covering the accounting postings for all three payment
methods, price history math, multi-supplier comparison, permission
boundaries, secure invoice storage, and the bank reconciliation
extension. Ended with a real rendered screenshot showing correct stock
accumulation (3 → 33 units across two purchases), correct price change
percentage, and correct supplier comparison — not just unit-level
assertions.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260831-v48.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Does not reset the database, run migrate:fresh, touch .env, or
regenerate APP_KEY. All new tables and columns are purely additive.
