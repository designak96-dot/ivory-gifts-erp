# Ivory Gifts ERP — PO Removal + Multi-Line Raw Material Purchases — 2026-08-31-v49

## 1. Old Purchase Order system — removed completely

Before deleting anything, a full-codebase dependency audit was run (not
assumed) and found three real dependents that would have broken
silently:
- The Calendar's "expected receipt" event, sourced from PO delivery
  dates — removed; the new direct-purchase system has no equivalent
  "awaiting delivery" state since purchases are recorded immediately.
- The Purchases CSV export — repointed to real `RawMaterialPurchase`
  data rather than deleted, so the export feature still works.
- Sales Product edit page's supplier price history, which was built on
  PO line items — removed, since Sales Products are no longer purchased
  through any PO mechanism, which also strengthens the Sales
  Products/Raw Materials separation you asked for.

All three were fixed first. Then: `PurchaseOrderController`, both
models, both views, every PO route, and `Supplier::purchaseOrders()`
were deleted, and `purchase_orders`/`purchase_order_items` are dropped
via a migration verified clean on a fresh database. Four old test files
that directly exercised the PO system were removed or had their
PO-specific assertions cut, keeping the parts still testing valid
functionality.

## 2. Raw Material Purchases — now genuinely multi-line

One supplier invoice can now cover multiple materials in a single
purchase — a real header + multiple lines, not one purchase per
material. This was the harder half of this update, and the part most
likely to matter for your actual data:

**The upgrade path was proven against real data, not assumed.** A
standalone test recreated the previous single-line schema, inserted a
real purchase row exactly as it would exist on your live site, ran the
conversion migration against it, and confirmed byte-for-byte correct
output — the new line preserves every original value, the header's
subtotal is computed correctly, and the old single-line columns are
cleanly removed. No `doctrine/dbal` package is available in this
environment, so the migration avoids `renameColumn`/`change()`
entirely, using only add-column, copy-data, and drop-column — standard
operations that don't need it.

On save: stock increases for every line's material, one purchase header
with multiple lines is saved, supplier/material price history updates,
latest cost updates, and one consolidated accounting entry posts —
verified that a 2-material purchase produces exactly one journal entry
(Inventory debit, VAT debit, Cash/Bank/Payable credit), not one entry
per line. No separate Expense record is ever created.

**Purchases & Suppliers now contains exactly the five things
requested** — Suppliers, Raw Materials, Record Purchase (a dynamic
add/remove line-item form), Purchase History, and Supplier Price
Comparison (per material, on its detail page) — nothing else. Fixing
this also surfaced and fixed a chain of breakage I traced myself:
`RawMaterial`'s relation to its purchases pointed at a column that no
longer existed after the schema change, the bank reconciliation
matching service still called the old single-argument purchase
signature, and two more test files had the same stale call — all found
via direct sweeps of the codebase, not assumed fixed.

## Testing
**509/509 automated tests passing.** Verified with a real rendered
screenshot: a genuine two-material purchase (Acrylic Sheet + Vinyl
Roll) shows as one history row with the correct combined total (AED
399.00), both materials' stock and latest cost updated correctly, and
the dynamic purchase form rendering and calculating live. Fresh-install
migration confirmed clean.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260831-v49.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

This update is intentionally destructive in one specific place — the
old PO tables are dropped, per your explicit "I do not need any old
Purchase Order history/data" instruction. Every other change in this
update, including the raw material purchase schema conversion, is
additive/data-preserving. Does not reset the database, run
migrate:fresh, touch .env, or regenerate APP_KEY.
