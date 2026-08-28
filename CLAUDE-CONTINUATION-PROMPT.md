# Claude continuation prompt — Ivory Gifts ERP

You are modifying an existing, working Laravel application supplied as `ivory-gifts-erp-cpanel-ready.zip`. Do not replace it with a prototype and do not move it into WordPress. Inspect the complete application, migrations, tests, deployment scripts, existing UI, and Composer lock file before changing code.

## Goal

Finish Ivory Gifts ERP as the single system for sales, quotations, invoices, payments, production, inventory, customers, products, and delivery management. The WordPress delivery plugin will no longer be used. Preserve everything that already works, then implement the features below as production-quality Laravel code.

## Non-negotiable hosting contract

- Laravel application: `/home/ivorygif/ivory-accounts`
- Public document root: `/home/ivorygif/public_html/accounts`
- URL: `https://accounts.ivorygifts.ae`
- PHP CLI: `/opt/alt/php84/usr/bin/php` (PHP 8.4.1 or newer)
- MySQL database configured only through `.env`
- UAE timezone: `Asia/Dubai`
- Only public bridge files/assets may exist under `public_html/accounts`.
- Keep `vendor` dependencies and compiled frontend assets in deliverables.
- Never include a real `.env`, credentials, customer data, or secrets in a ZIP.

## Existing features that must remain working

- First Owner setup, authentication, roles and permissions.
- UAE live time/date and dashboard charts.
- Customers, products, quotations, sales orders, invoices, payments, VAT, expenses, accounting, inventory, purchasing, production, reports, audit log, backups, and system health.
- Sales Order automatically creates/updates its delivery schedule from the selected delivery date.
- Delivery dashboard with month/date/emirate/search filters, working list, overdue and today views, priority, confirmation status, design status, driver, delivery status, inline updates, delivery reports, and separate legacy-import history.
- Sales Order create/edit with inline Add Customer and Add Product dialogs.
- Selecting a customer auto-fills phone, address, emirate/country and area.
- Customer codes and product SKUs remain internal auto-generated identifiers and are not required from users.
- UAE emirates, Pickup, and other GCC destinations.
- Existing orders can be fully edited and rescheduled.
- Empty or stale product rows are ignored; valid order items save with correct VAT and totals.

Do not regress these behaviors. Add automated tests for every new behavior and retain all existing tests.

## 1. Company branding and document settings

Add an Owner-only Settings section for:

- company logo upload/change/remove;
- company legal name, trade name, TRN, address, phone, email, website;
- bank/payment details, default quotation terms, invoice terms, document footer, and authorized-signature image.

Accept safe PNG, JPEG, WebP, or SVG logo files with strict MIME, size, and SVG sanitization. Store persistent branding files under `storage/app/public/branding`, never inside compiled assets. The logo and settings must survive upgrades.

Use the configured branding consistently in the sidebar/login header and on professional A4 print/PDF-ready templates for:

- quotation;
- tax invoice;
- delivery note.

Documents must show the correct customer/delivery details, company TRN, AED currency, VAT breakdown, totals, terms, footer, and logo. Delivery notes should support an optional mode that hides prices. Keep browser print support and add a reliable server-side PDF download only if the chosen package is compatible with cPanel/PHP 8.4 and is included in `vendor`.

## 2. Product catalogue import with photos and prices

I will attach the real `products.json` from my WhatsApp order form and its related `assets`, `uploads`, or image folders. Do not guess the JSON schema from the filename or screenshot. Inspect the real JSON and image paths first.

Build an Owner-only Product Import wizard that accepts either the JSON file or one ZIP containing `products.json` plus its related image folders. It must provide:

- dry-run and preview before writing;
- field mapping for stable source ID, English/Arabic names, category, description, sale price, VAT, active status, options/variations, and image path;
- safe image extraction, MIME/size validation, sanitized filenames, image copying to persistent storage, thumbnails, and a missing-image report;
- idempotent upsert using a stable source identifier so importing twice does not duplicate products;
- transactions or bounded batches, import log, row-level errors, final counts, and batch rollback where safe;
- product list/detail/selection displays with product photo, name, and price;
- no mandatory visible SKU.

Do not fetch unknown remote image URLs automatically. Show them as unresolved unless the Owner explicitly authorizes a trusted source.

## 3. Old customer and order data import

Add reusable import wizards for customers and historical orders from CSV, XLSX, or JSON. Include preview, column mapping, dry-run, validation, duplicate matching, import logs, error downloads, and idempotent re-import.

- Match customers primarily by normalized phone, then email, then explicit source ID.
- Match orders by external/source order number.
- Preserve original dates, statuses, amounts, notes and source identifiers.
- Allow UAE emirates and all GCC countries.
- Orders with a delivery date must appear automatically in the ERP delivery schedule.
- Historical/plugin records that are not real ERP sales orders must remain clearly separated from normal sales/accounting totals.
- Never overwrite a newer ERP value silently; show conflicts for review.

## 4. Complete native delivery system

Use the supplied WordPress plugin ZIP/screenshots only as a functional reference. Implement the delivery system natively in Laravel and MySQL; do not call or depend on WordPress, WooCommerce, `delivery_api.php`, or the old plugin database.

The native delivery dashboard must include:

- selected month and selected date;
- cards for total orders, overdue, waiting deposit/confirmation, and need design;
- tabs for working list, overdue, today, and minimum daily capacity where available;
- filters for date, emirate/GCC destination, driver, status, and search by order/customer/phone/area;
- rows showing priority, order, customer and phone, delivery date, emirate/area, confirmation, design, driver, status, and actions;
- clear overdue/urgent visual states;
- inline status/driver changes with validation, audit trail, and optimistic UI recovery;
- create/edit delivery details from the Sales Order and full editing of old orders;
- drivers, delivery calendar, proof of delivery, recipient name, signature/photo attachments, notes, and activity log;
- daily capacity setting, collision warning, and reports without blocking an authorized Owner override.

Manual Sales Order entry is the source of truth. WooCommerce import is optional and must never be required. Creating or changing an order's delivery date must create or reschedule exactly one linked delivery record without duplicates.

## 5. Safer future updates

All schema changes must use additive, versioned migrations. Never use `migrate:fresh`, `db:wipe`, destructive reseeding, or delete production business data. Do not rename/drop a live column in one step; use staged compatible migrations and data backfills.

Persistent items that an update must never replace are:

- `.env`;
- MySQL business data;
- `storage/app/public` branding, product images, POD files, and uploads;
- `storage/app/private/backups`;
- user-created templates/settings.

For every release, produce both:

1. `ivory-gifts-erp-cpanel-ready.zip` — complete fresh-install application with `vendor`, compiled assets, `.env.example`, migrations/seeders, cPanel public bridge, secure `deploy.sh`, verification, cron, backup and rollback instructions.
2. `ivory-gifts-erp-update-YYYYMMDD-vN.zip` — incremental update with a manifest, changed files only, additive migrations, compiled assets, one `update.sh`, one rollback script, and instructions.

The incremental updater must validate PHP/extensions and `.env`, test the database, create a MySQL backup, create a timestamped backup of every file in its manifest, enter maintenance mode, copy files, run `php artisan migrate --force`, rebuild config/route/view caches, sync the cPanel public bridge, run verification, and always leave maintenance mode on failure. It must display a clear success/failure result. The rollback script must restore the backed-up files and explicitly explain database rollback limitations.

The fresh installer must preserve its current one-command experience:

```bash
cd /home/ivorygif/ivory-accounts
PHP_BIN=/opt/alt/php84/usr/bin/php bash deploy.sh
```

## Security and quality requirements

- Authorization on every write route; Owner-only imports/settings/system operations.
- CSRF protection, server-side validation, safe mass assignment, transactions for multi-table writes, and audit logs.
- Uploaded archives must be protected against path traversal, executable files, oversized decompression, and malicious MIME/SVG content.
- No debug mode, default passwords, hard-coded credentials, or secrets in source.
- Responsive desktop/tablet UI consistent with the current Ivory Gifts design.
- Use actual database values; do not add fake dashboard records.
- Run PHP syntax checks, migrations from an empty database, migration/update rehearsal from the current schema, all automated tests, route/cache/view compilation, JavaScript syntax checks, and `verify-install.sh`.

## Required final response

Do the implementation and testing, not only a plan. Return the two tested ZIP files with checksums and concise installation/update steps. Also list the exact tests run and any feature that could not be completed because the real source data was not supplied.

If `products.json`, its photos, the old customer/order exports, or the plugin reference ZIP are missing, ask me to attach them. Do not invent their data structure and do not claim their import was tested without the real files.
