# Update v73 — Dashboard drill-down + paid/outstanding colours (includes v71 + v72)

New in v73:
- Every dashboard box is clickable and opens only the records behind that number:
  Orders / Sales / Net value → orders of the selected month
  Outstanding invoices / Receivables → unpaid invoices only (with total)
  Month expenses → expenses of the selected month (with total)
  Deliveries today → today's deliveries · Active production → open jobs only
  Total customers → customers · Gross profit → report for the month
  Low stock → products at/below reorder level
- Filtered lists show a "Filtered: ... — Show all ✕" bar.
- Invoices list, invoice page and customer page:
  Paid 0 = red, Paid > 0 = green; Outstanding 0 = green, Outstanding > 0 = red.
- Dashboard "Outstanding invoices" no longer counts cancelled invoices.

Also includes v71 (order sheet import) and v72 (monthly finance import).
No database migrations.
