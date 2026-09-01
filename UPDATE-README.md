# Ivory Gifts ERP — Sales Orders Table Polish — 2026-08-31-v51

## Three fixes from your screenshot feedback

**1. Proof widget now sits below the order number**, on its own line,
instead of crowding the same line as the order number and badges.

**2. Long customer names truncate to one line with an ellipsis**
instead of wrapping across 2-3 lines and bloating row height — hover
over a truncated name to see the full name in a tooltip. Verified the
full name is still in the page markup (CSS truncates the *display*,
never the data).

**3. The Amount column is decluttered.** Previously every row showed
Total, then a Paid/Remaining line, then a status badge — even when
Paid/Remaining was pure repetition of what the badge already said (e.g.
"Unpaid" + "Paid AED 0.00 · Remaining AED 210.00" is the same fact
twice). Now the Paid/Remaining breakdown only appears for genuinely
**partially paid** orders, where it's the one case that actually adds
information. Fully paid and fully unpaid orders show just the total and
the badge — cleaner, and the numbers that matter are still one glance
away when they matter.

**Verified with a real screenshot** reproducing your exact scenario —
the same long name, a confirmed order with proof attached, and a
partially-paid order — confirming all three fixes render correctly
together, not just in isolation.

## Testing
**516/516 automated tests passing**, including 5 new tests specifically
guarding these three behaviors — proof widget placement, name
truncation with tooltip preservation, and each of the three payment
states (paid/unpaid/partial) rendering the amount cell correctly.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260831-v51.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

Does not reset the database, run migrate:fresh, touch .env, or
regenerate APP_KEY. Small, targeted change — only the Sales Orders list
view and shared CSS were touched.
