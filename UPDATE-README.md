# Update v72 — Monthly finance import (+ includes v71 order import fix)

New in v72 (Finance & Order Migration Import):
- "Month of this sheet" field (auto-filled from a filename like august-expenses.csv).
- Rows with a blank date are dated the 1st of that month — never today's date.
- Dates are read day-first (15/08/2026 = 15 August); 2026-08-15 and "15 Aug" also work.
- Rows dated outside the chosen month are kept and flagged in the preview.
- Unreadable dates block the import instead of guessing.
- An order sheet uploaded as a finance import is blocked with a clear message.

Also includes everything from v71 (historical order sheet import fix).
Changed files: see update-manifest.txt. No database migrations.
