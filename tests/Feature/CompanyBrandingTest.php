<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Brand Owner', 'email' => 'brand-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    public function test_owner_can_save_text_branding_fields(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('settings.branding'), [
            'company_legal_name' => 'Ivory Gifts Trading LLC',
            'company_trade_name' => 'Ivory Gifts',
            'company_website' => 'https://ivorygifts.ae',
            'quotation_terms' => '50% deposit required.',
            'invoice_terms' => 'Payment due within 7 days.',
            'document_footer' => 'Thank you for choosing Ivory Gifts.',
        ])->assertRedirect();

        $this->assertSame('Ivory Gifts Trading LLC', Setting::value('company_legal_name'));
        $this->assertSame('50% deposit required.', Setting::value('quotation_terms'));
    }

    public function test_owner_can_upload_a_png_logo_and_it_is_shared_to_views(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $file = UploadedFile::fake()->image('logo.png', 200, 80);

        $this->actingAs($owner)->post(route('settings.branding'), ['logo' => $file])->assertRedirect();

        $path = Setting::value('logo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('sidebar-logo', false);
    }

    public function test_logo_removal_deletes_the_file_and_clears_the_setting(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('settings.branding'), ['logo' => UploadedFile::fake()->image('logo.png')]);
        $path = Setting::value('logo_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($owner)->delete(route('settings.branding.logo.remove'))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Setting::value('logo_path'));
    }

    public function test_malicious_svg_script_tag_is_stripped_on_upload(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $malicious = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10" onclick="alert(2)"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $malicious)->mimeType('image/svg+xml');

        $this->actingAs($owner)->post(route('settings.branding'), ['logo' => $file])->assertRedirect();

        $path = Setting::value('logo_path');
        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
    }

    public function test_non_owner_cannot_update_branding(): void
    {
        $this->seed(SystemDataSeeder::class);
        $sales = User::create(['name' => 'Sales', 'email' => 'sales-brand@example.test', 'password' => 'test-password', 'is_active' => true]);
        $sales->roles()->attach(Role::where('name', 'sales')->firstOrFail());

        $this->actingAs($sales)->post(route('settings.branding'), ['company_legal_name' => 'Hacked LLC'])->assertForbidden();
        $this->assertNull(Setting::value('company_legal_name'));
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $file = UploadedFile::fake()->create('big-logo.png', 4000); // 4MB, over the 3MB limit

        $this->actingAs($owner)->post(route('settings.branding'), ['logo' => $file])->assertSessionHasErrors('logo');
    }
}
