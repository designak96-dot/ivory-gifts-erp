# Ivory Gifts ERP — Consolidated Delivery Finance Hub + Real Automation Fix — 2026-09-05-v63

## Two real things fixed here, not one

### 1. The automation you asked about wasn't actually wired up

I built and tested the driver-fee and daily-allowance logic in v62, but
checking the actual delivery-completion flow (`DeliveryController::update()`)
showed it was **never called** when a delivery is genuinely marked
Delivered through the normal screen — only when the service method was
called directly, which my own tests did, but the real app never did.
This is exactly what you'd have experienced as "the automation isn't
working."

**Fixed**: marking an Own Company delivery as Delivered now
automatically applies the AED 10 driver fee and the shared daily AED 5
allowance, inside the same save — not a separate step to remember.
Reverting a delivery away from Delivered (failed/cancelled) correctly
removes the fee and recalculates the day's allowance too, rather than
leaving a stale fee behind.

Verified with three new tests that go through the **real endpoint**,
not the service directly: marking one delivery delivered applies AED
10 + AED 5 automatically; five real completions in one day still
produce exactly one AED 5 allowance split five ways; reverting a
delivery's status correctly removes what was auto-applied.

### 2. Too many separate nav items — consolidated into one

**Courier Bills, Driver Settlements, Vehicle Expenses, and Delivery
Finance Settings are now one "Delivery Finance" page with tabs**,
instead of four separate sidebar links. Same underlying features, same
routes still work if you have them bookmarked — just one entry point
instead of four.

### Drivers & Vehicles — the part that was missing entirely

New **"Drivers & Vehicles" tab** on that same page. Adding a driver was
previously a multi-step trip through Users & Roles (create user, assign
role, etc.) — now it's name + phone, one button, done, and the new
driver is immediately available in every delivery-assignment dropdown.
Vehicles work the same way. Verified directly: a driver added through
this quick form already has the correct role attached and shows up
immediately.

## Testing
**134/134 of my new and touched tests passing** — 7 new this round (3
on the real automation wiring, 4 on the consolidated hub and quick-add
flows). Same 11 pre-existing, unrelated test failures as every prior
update. Verified visually with real screenshots: the new single
"Delivery Finance" nav item, and the Drivers & Vehicles tab showing a
freshly-added driver and vehicle side by side.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260905-v63.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

The old direct URLs (`/courier-bills`, `/driver-settlements`, etc.)
still work exactly as before — nothing was removed, only the sidebar
entry point was consolidated. Does not reset the database, run
migrate:fresh, touch .env, or regenerate APP_KEY.
