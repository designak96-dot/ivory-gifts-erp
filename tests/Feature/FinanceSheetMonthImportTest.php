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
}
