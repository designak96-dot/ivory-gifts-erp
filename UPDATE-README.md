# Update v74 — Customer order history shows imported orders (includes v71–v73)

New in v74:
- Customer page "Order history" now lists imported (sheet) orders too, marked "Imported".
- Customer "Orders" card total includes imported orders.
- Paid 0 = red / Remaining 0 = green in the customer order history.

---
Previous — v73: Dashboard drill-down + paid/outstanding colours

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
