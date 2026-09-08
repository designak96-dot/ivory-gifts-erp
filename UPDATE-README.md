# Ivory Gifts ERP — Fix Driver Fee Not Applying via Delivery Finance Section — 2026-09-07-v67

## What your screenshot showed

Order 01-0926: AED 40 charge, "Provider: Akbar Sha" (a driver), but
**Profit/Loss showed the full AED 40** — no AED 10 fee deducted at all.

## Why — a real, genuine bug

The AED 10 driver fee automation only fired from one specific place:
the main "mark Delivered" status-update action. But `delivery_type`
and the driver are very often actually set or confirmed **afterward**,
through the delivery's own "Delivery Finance" section — and that form
never triggered the fee logic at all.

So if a delivery was already marked Delivered (from before this
automation existed, or before its type was ever confirmed), and you
then went into Delivery Finance to set "Own Company Driver" and pick
Akbar Sha — nothing applied the fee. It would show AED 0 driver fee
forever, with no obvious way to fix it short of un-delivering and
re-delivering the order, which isn't something you'd think to do for
an order that's already correctly delivered.

## Fixed

Saving the Delivery Finance section now applies the same AED 10 fee +
AED 5 daily allowance automation the moment all three things are
genuinely true — delivered, Own Company, and a driver assigned —
regardless of which screen made that true. Verified directly with your
exact scenario: an already-delivered order, driver already assigned,
type set to Own Company via this form — the fee now applies
immediately, and profit correctly becomes AED 25 (40 − 10 − 5), not
the full AED 40. Re-saving the form again afterward does not
double-apply the fee — verified directly.

## For the order in your screenshot specifically

Once this is installed, open that delivery's page and re-save its
Delivery Finance section (even without changing anything) — the fee
should apply immediately, since it now checks "is this genuinely a
delivered, own-company delivery with a driver, but the fee hasn't been
applied yet" every time that form is saved.

## How to move a delivery from "Unsettled" to paid

This isn't a bug — it's the actual intended two-step process:

1. Go to **Delivery Finance → Driver Settlements**, pick the driver
   (e.g., Akbar Sha) and a date range that includes this delivery, and
   click **Preview** — this shows every unsettled fee/allowance for
   that driver in that period.
2. Click **Confirm & Create Settlement** — this locks those specific
   deliveries into one settlement record (so they can't accidentally
   end up in a second settlement later).
3. Open that settlement and click **Record Payment** — enter the
   amount, date, method, and upload proof (proof is required). Once
   paid, the delivery's status changes from "Unsettled" to showing the
   settlement's status (Paid/Partially Paid) instead.

The **Daily Deliveries** tab you're looking at doesn't have a
"mark settled" button directly on it — settling happens through Driver
Settlements specifically, so one settlement can properly cover many
deliveries at once with one real payment, rather than paying each
delivery one at a time.

## Testing
**172/172 tests passing** (2 new), same 11 pre-existing unrelated
failures as every prior update.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260907-v67.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Backend-only fix — no migration needed. Does not reset the database,
run migrate:fresh, touch .env, or regenerate APP_KEY.
