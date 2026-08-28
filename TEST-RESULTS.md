# Test Results — this release

Real output from `php artisan test`, captured immediately before packaging.

```
     PASS  Tests\Unit\DocumentTotalsTest
    ✓ vat and discount are rounded per line
     PASS  Tests\Feature\CompanyBrandingTest
    ✓ owner can save text branding fields                                  0.43s  
    ✓ owner can upload a png logo and it is shared to views                0.13s  
    ✓ logo removal deletes the file and clears the setting                 0.08s  
    ✓ malicious svg script tag is stripped on upload                       0.08s  
    ✓ non owner cannot update branding                                     0.08s  
    ✓ oversized file is rejected                                           0.07s  
     PASS  Tests\Feature\DataImportTest
    ✓ customer import creates new customers from csv                       0.08s  
    ✓ customer import matches existing by phone and updates                0.08s  
    ✓ customer import never overwrites a manually created customer matche… 0.08s  
    ✓ customer import is idempotent on re run                              0.08s  
    ✓ order import creates order and links exactly one delivery note       0.09s  
    ✓ re importing the same order does not duplicate the delivery note     0.09s  
    ✓ xlsx is explicitly rejected with a clear message                     0.01s  
    ✓ only owner can reach the import wizard                               0.08s  
     PASS  Tests\Feature\DeliveryManagementTest
    ✓ owner can see live delivery calendar and reports                     0.12s  
    ✓ scheduler moves to next day when daily limit is full                 0.07s  
     PASS  Tests\Feature\DocumentBrandingTest
    ✓ quotation print shows company trn and terms                          0.11s  
    ✓ invoice print shows bank details and terms                           0.15s  
    ✓ delivery note hides prices by default when configured                0.12s  
    ✓ delivery note shows prices when toggled on                           0.10s  
    ✓ logo appears on all three document types when configured             0.09s  
     PASS  Tests\Feature\InstallationTest
    ✓ first owner setup is one time only                                   0.09s  
     PASS  Tests\Feature\OrderBalanceDisplayTest
    ✓ paid and remaining are computed correctly                            0.02s  
    ✓ unpaid order shows correct status                                    0.01s  
    ✓ fully paid order shows correct status                                0.01s  
    ✓ customer profile shows order history with correct balances           0.09s  
    ✓ orders list shows paid and remaining columns                         0.09s  
     PASS  Tests\Feature\OrderNumberingTest
    ✓ generates manual xxxx mmyy format                                    0.08s  
    ✓ sequence increments within the same month regardless of manual valu… 0.07s  
    ✓ sequence resets for a new month                                      0.07s  
    ✓ january and february sequences are independent even out of order     0.07s  
    ✓ two concurrent requests never receive the same order number          0.07s  
    ✓ order number is rejected as duplicate at the database level          0.07s  
    ✓ invoice number matches sales order number with inv prefix            0.09s  
    ✓ a second orders invoice never reuses the first orders number         0.09s  
     PASS  Tests\Feature\ProductCsvImportTest
    ✓ csv import creates a new product                                     0.08s  
    ✓ csv import updates existing product matched by sku                   0.08s  
    ✓ csv import does not blank out an existing image when row has none    0.08s  
    ✓ only owner can download templates                                    0.07s  
    ✓ owner can download csv template                                      0.07s  
     PASS  Tests\Feature\ProductImportTest
    ✓ preview reads the real products json without writing anything        0.02s  
    ✓ dry run validates all 84 real products and writes nothing            0.11s  
    ✓ commit creates all 84 real products with generated skus              0.24s  
    ✓ running the same import twice does not duplicate products            0.29s  
    ✓ only owner role can reach the import wizard                          0.08s  
    ✓ real zip extracts safely and matches images by basename              0.63s  
     PASS  Tests\Feature\ProofRequirementsTest
    ✓ payment is rejected without proof                                    0.08s  
    ✓ payment succeeds with proof and is privately stored                  0.09s  
    ✓ proof file is not publicly reachable without auth                    0.09s  
    ✓ expense is rejected without proof                                    0.07s  
    ✓ expense succeeds with proof                                          0.08s  
     PASS  Tests\Feature\SalesWorkflowTest
    ✓ quotation to paid invoice posts balanced journals                    0.18s  
    ✓ read only user cannot create customer                                0.07s  
     PASS  Tests\Feature\ScreenSmokeTest
    ✓ owner can render every main screen                                   0.34s  

Tests: 55 passed (205 assertions)
```

## Final verification performed (per the explicit checklist)

- **Fresh empty-database migration**: all 14 migrations apply cleanly, zero errors.
- **Upgrade rehearsal from the actual pre-session schema**: migrated the
  original uploaded ERP's schema, inserted real rows (a customer, a product
  with the old `AUTO-` SKU format, a sales order, a paid invoice, and a
  historical expense with no proof), then applied only this release's 4 new
  migrations on top. Every row — customer, product, order, invoice, expense
  — was queried back afterward and confirmed unchanged. The old `AUTO-`
  SKU format was specifically confirmed untouched, per the explicit
  requirement that existing SKUs never change on their own.
- **JavaScript syntax**: `app.js`, `order-entry.js`, `delivery.js` all pass
  `node --check`.
- **Composer platform requirements**: confirmed the shipped
  `vendor/composer/platform_check.php` still requires PHP ≥ 8.4.1,
  unmodified — my own test runs in this sandbox (PHP 8.3) used a disposable
  scratch copy with a local-only patch, verified never present in either
  final ZIP.
- **Route cache, config cache, Blade cache**: all three build successfully
  with no errors (111 routes).
- **`verify-install.sh`**: present and unmodified from the base release;
  exercises vendor presence, public bridge files, compiled assets, storage
  link, and the app's own `ivory:verify` artisan command.

## What could not be executed in this environment, stated plainly

- **A live deployment rehearsal against real cPanel/PHP 8.4.1** — this
  sandbox only has PHP 8.3 available and no route to your actual server.
  Every individual step `deploy.sh`/`update.sh` performs was verified
  independently (migrations, artisan commands, DB connectivity pattern),
  but the true end-to-end run happens on your first real execution.
- **The incremental updater and rollback scripts themselves were not run
  end-to-end** in this session (only their predecessors were, in an earlier
  session) — they are unchanged from the prior verified version except for
  the manifest file list, which was rebuilt from a real `diff` against the
  original upload, not typed from memory.
