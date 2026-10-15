<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FinanceSheetMonthImportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Fin Owner', 'email' => 'fin-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        $this->actingAs($owner);
        return $owner;
    }

    private function csv(array $rows, string $name = 'august-expenses.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fin').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, ['date', 'expense_category', 'invoice no', 'description', 'payee', 'amount', 'total_amount + tax', 'supplier', 'payment method']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function augustSheet(): UploadedFile
    {
        return $this->csv([
            ['', 'Rent', 'R-08', 'Shop rent', 'Landlord', '5000', '5000', '', 'bank'],
            ['15/08/2026', 'General', 'INV-1', 'Printer ink', '', '200', '210', 'Sharaf DG', 'card'],
            ['2026-07-30', 'General', 'INV-2', 'Late July bill', '', '100', '100', 'Shop', 'cash'],
        ]);
    }

    public function test_blank_dates_use_the_chosen_month_and_slash_dates_are_day_first(): void
    {
        $this->owner();
        $this->post(route('imports.finance.preview'), ['type' => 'expenses', 'sheet_month' => '2026-08', 'file' => $this->augustSheet()])
            ->assertOk()
            ->assertSee('August 2026')
            ->assertSee('1 row(s) had no date — dated 1 Aug 2026.')
            ->assertSee('Dated outside August 2026');

        $this->post(route('imports.finance.commit'), ['reviewed_confirmation' => '1'])->assertRedirect();

        $dates = Expense::orderBy('id')->get()->map(fn ($e) => \Carbon\Carbon::parse($e->expense_date)->toDateString())->all();
        $this->assertSame(['2026-08-01', '2026-08-15', '2026-07-30'], $dates, 'Never today, 15/08 is 15 August, other dates kept.');
    }

    public function test_sheet_month_is_required(): void
    {
        $this->owner();
        $this->post(route('imports.finance.preview'), ['type' => 'expenses', 'file' => $this->augustSheet()])
            ->assertSessionHasErrors('sheet_month');
    }

    public function test_unreadable_date_blocks_commit(): void
    {
        $this->owner();
        $this->post(route('imports.finance.preview'), ['type' => 'expenses', 'sheet_month' => '2026-08', 'file' => $this->csv([['next week', 'General', '', 'x', '', '10', '10', '', 'cash']])])
            ->assertOk()
            ->assertSee('could not be read')
            ->assertDontSee('Confirm Import');
    }

    public function test_order_sheet_uploaded_as_finance_is_blocked(): void
    {
        $this->owner();
        $path = tempnam(sys_get_temp_dir(), 'ord').'.csv';
        file_put_contents($path, "source_order_number,,customer_phone,item_description,item_price,amount\n1,Sara,0501234567,Cups,75,75\n");
        $this->post(route('imports.finance.preview'), ['type' => 'material_purchases', 'sheet_month' => '2026-07', 'file' => new UploadedFile($path, 'july.csv', 'text/csv', null, true)])
            ->assertOk()
            ->assertSee('looks like an ORDER sheet')
            ->assertDontSee('Confirm Import');
    }
    public function test_july_currency_amounts_survive_csv_preview_commit_and_expense_register(): void
    {
        $this->owner();
        $this->post(route('imports.finance.preview'), [
            'type' => 'expenses', 'sheet_month' => '2026-07',
            'file' => $this->csv([
                ['31/07/2026', 'General', 'JUL-1', 'Petrol for Delivery', 'Adnoc/Akbar', 'AED 50.00', 'AED 52.50', '', 'bank'],
                ['31/07/2026', 'General', 'JUL-2', 'Internet for Delivery', 'Etisalat/Akbar', '1,200.00', '', '', 'bank'],
                ['31/07/2026', 'Salaries', 'JUL-3', 'July salary', 'Ahmed', 'AED 3,000', '', '', 'bank'],
            ], 'july-expenses.csv'),
        ])->assertOk()->assertSee('AED 4,250.00');

        $this->post(route('imports.finance.commit'), ['reviewed_confirmation' => '1'])->assertRedirect();
        $this->assertDatabaseHas('expenses', ['reference' => 'JUL-1', 'amount_ex_tax' => 50, 'tax_amount' => 2.5, 'total_amount' => 52.5]);
        $this->assertDatabaseHas('expenses', ['reference' => 'JUL-2', 'amount_ex_tax' => 1200, 'tax_amount' => 0, 'total_amount' => 1200]);
        $this->assertDatabaseHas('expenses', ['reference' => 'JUL-3', 'amount_ex_tax' => 3000, 'tax_amount' => 0, 'total_amount' => 3000]);
        $this->get(route('expenses.index', ['month' => '2026-07']))->assertOk()
            ->assertSee('AED 52.50')->assertSee('AED 1,200.00')->assertSee('AED 3,000.00')->assertSee('AED 4,252.50');
    }

    public function test_invalid_amount_blocks_even_a_direct_commit_or_dry_run(): void
    {
        $this->owner();
        $this->post(route('imports.finance.preview'), [
            'type' => 'expenses', 'sheet_month' => '2026-07',
            'file' => $this->csv([['31/07/2026', 'General', '', 'Petrol', '', 'AED invalid', '', '', 'bank']]),
        ])->assertOk()->assertSee('Invalid Amount')->assertDontSee('Confirm Import');

        $this->post(route('imports.finance.commit'), ['reviewed_confirmation' => '1'])->assertSessionHasErrors('file');
        $this->post(route('imports.finance.dry-run'))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('data_imports', 0);
    }

    public function test_material_purchases_and_income_use_the_same_money_parser(): void
    {
        $owner = $this->owner();
        $service = app(\App\Services\FinanceMigrationImportService::class);
        $purchase = $service->commitMaterialPurchases([
            ['date' => '2026-07-31', 'category' => 'Material Purchases (COGS)', 'invoice_no' => 'JUL-MAT',
                'supplier' => 'Test Supplier', 'description' => 'Vinyl', 'amount' => 'AED 1,000', 'total_amount_tax' => ''],
        ], $owner->id, false);
        $this->assertSame(0, $purchase->error_count);
        $this->assertDatabaseHas('raw_material_purchases', ['payment_reference' => 'JUL-MAT', 'subtotal' => 1000, 'tax_amount' => 0, 'total_amount' => 1000]);

        $income = $service->commitIncome([
            ['date' => '2026-07-31', 'description' => 'Delivery fees', 'amount' => 'AED 1,000', 'total_amount_tax' => 'AED 1,050'],
        ], $owner->id, false, 'ivory_delivery');
        $this->assertSame(0, $income->error_count);
        $this->assertDatabaseHas('income_records', ['description' => 'Delivery fees', 'amount_ex_tax' => 1000, 'tax_amount' => 50, 'total_amount' => 1050]);
    }

}
