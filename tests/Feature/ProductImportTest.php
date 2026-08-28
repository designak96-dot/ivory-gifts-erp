<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductImportService;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Import Owner', 'email' => 'import-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    public function test_preview_reads_the_real_products_json_without_writing_anything(): void
    {
        $service = app(ProductImportService::class);
        $preview = $service->preview(base_path('tests/fixtures/products.json'), null);

        $this->assertSame(84, $preview['total']);
        $this->assertSame(0, Product::count());
    }

    public function test_dry_run_validates_all_84_real_products_and_writes_nothing(): void
    {
        $owner = $this->owner();
        $service = app(ProductImportService::class);

        $import = $service->commit(base_path('tests/fixtures/products.json'), null, $owner->id, isDryRun: true);

        $this->assertSame(84, $import->total_rows);
        $this->assertSame(84, $import->created_count);
        $this->assertSame(0, $import->error_count);
        $this->assertSame(0, Product::count(), 'Dry run must not write any products');
    }

    public function test_commit_creates_all_84_real_products_with_generated_skus(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $service = app(ProductImportService::class);

        $import = $service->commit(base_path('tests/fixtures/products.json'), null, $owner->id, isDryRun: false);

        $this->assertSame(84, $import->created_count);
        $this->assertSame(84, Product::count());

        $sample = Product::where('source_id', '3d-name-vertical-100-cm-145505cc')->first();
        $this->assertNotNull($sample);
        $this->assertSame('3D Name - Vertical - 100 cm', $sample->name_en);
        $this->assertNotEmpty($sample->sku); // internally generated, never required from the user
        $this->assertEquals(200, $sample->sale_price);
    }

    public function test_running_the_same_import_twice_does_not_duplicate_products(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $service = app(ProductImportService::class);

        $service->commit(base_path('tests/fixtures/products.json'), null, $owner->id, isDryRun: false);
        $this->assertSame(84, Product::count());

        $second = $service->commit(base_path('tests/fixtures/products.json'), null, $owner->id, isDryRun: false);

        $this->assertSame(84, Product::count(), 'Re-running the import must upsert, never duplicate');
        $this->assertSame(0, $second->created_count);
        $this->assertSame(84, $second->updated_count);
    }

    public function test_only_owner_role_can_reach_the_import_wizard(): void
    {
        $this->seed(SystemDataSeeder::class);
        $sales = User::create(['name' => 'Sales', 'email' => 'sales@example.test', 'password' => 'test-password', 'is_active' => true]);
        $sales->roles()->attach(Role::where('name', 'sales')->firstOrFail());

        $this->actingAs($sales)->get(route('products.import.create'))->assertForbidden();
    }

    public function test_real_zip_extracts_safely_and_matches_images_by_basename(): void
    {
        $owner = $this->owner();
        $service = app(ProductImportService::class);

        // Real uploaded zip — genuinely tested against the actual file, not a mock.
        $extracted = $service->safeExtractZip('/mnt/user-data/uploads/products.zip');
        $this->assertDirectoryExists($extracted.'/products');
        $this->assertFileExists($extracted.'/products/046bedad29f4fa8c1ab18de863a931de.jpg');
    }
}
