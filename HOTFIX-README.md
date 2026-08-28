# Hotfix v11 — customer phone search bug + dropdown clipping bug

## Bug 1: searching by local-format phone number found nothing (confirmed)
Your screenshot showed searching `0543927290` for "Juan" (stored as
`+971543927290`) returning "No match." Traced it exactly: the search
matched raw digits (including the leading 0) against a stored phone that
never has one (normalization always strips it). Fixed by also searching
the leading-zero-stripped digits. Verified with the exact values from
your screenshot as a test case, plus a negative-match control (an
unrelated number still correctly returns zero results) and confirmed the
test genuinely fails without the fix.

## Bug 2: search-results dropdown appeared cut off / had its own scrollbar
This is a real CSS bug, found by reading the actual stylesheet rather
than guessing: the items table wrapper has `overflow:auto` (needed for
horizontal scrolling on narrow screens), and **any element with
`position:absolute` inside a container with non-`visible` overflow gets
clipped to that container's bounds** — this is standard CSS behavior, not
a framework quirk. The product search-results dropdown is exactly such an
element, so it was being cut off by the table wrapper instead of floating
freely. Fixed by turning off clipping specifically for the order/quotation
items table wrapper (a small tradeoff: a very wide items table could
cause page-level horizontal scroll on a tiny screen, which is far less
disruptive than an unusable, half-hidden dropdown).

## Install
```bash
cd /home/ivorygif/ivory-accounts
unzip -o /path/to/this-file.zip -d .
PHP_BIN=/opt/alt/php85/usr/bin/php bash update.sh
```

## Testing
103/103 automated tests passing, including 5 new tests for the phone
search fix (verified the fix's test genuinely fails when the fix is
removed) and a structural test for the clipping fix.
