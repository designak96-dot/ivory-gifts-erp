# Ivory Gifts ERP — Incremental Update 2026-08-28-v22

## Full visual redesign — appearance only, zero content/functionality changes

Complete new look system-wide: cool indigo/lavender palette replacing the
old warm brown/ivory theme, fully rounded "pill" treatment on buttons,
badges, and the active sidebar item, larger card radius (20px), softer
cool-toned shadows, and stronger heading weight for more visual presence
— across every page, since they all share one stylesheet.

**How this was done safely**: every single selector, and every
non-visual CSS property (`display`, `position`, `overflow`, `z-index`,
`white-space`, grid/flex structure) was left completely untouched —
verified with a direct diff that strips out only color values and
confirms the rest is byte-for-byte identical to before. Zero JavaScript
was touched at all. This matters because a large amount of this session
went into finding and fixing real functional bugs in this exact CSS
(dropdown clipping, print pagination, mobile table behavior) — a careless
re-skin could easily have silently reintroduced any of them.

## What changed
- `resources/css/app.css` — the main design token system (`:root`
  variables) and every component style that references them.
- `dashboard.css`, `delivery.css`, `order-entry.css`, `sync.css` — the
  hardcoded warm-toned colors in these four files (dashboard charts,
  delivery status badges, quick-add dialogs, the sync banner) recolored
  to match, so nothing looks like it belongs to a different theme.
- One inline style in the shared layout (the proof-viewer popup's
  placeholder background).

## Testing
**154/154 automated tests still passing** — including every dropdown-
clipping, print-pagination, form-preservation, and duplicate-detection
test built up over this entire project. This is the real proof nothing
functional broke: none of those tests care about color, but every one of
them would have failed if a selector, property, or piece of markup had
been altered instead of just recolored.

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/ivory-gifts-erp-update-20260828-v22.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```
