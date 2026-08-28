<?php

namespace Tests\Feature;

use App\Models\{Product, Role, User};
use App\Services\ProductImportService;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'CSV Owner', 'email' => 'csv-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        return $owner;
    }

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'products').'.csv';
        file_put_contents($path, $content);
        return $path;
    }

    public function test_csv_import_creates_a_new_product(): void
    {
        $this->owner();
        $service = app(ProductImportService::class);
        $csv = "sku,name,category,description,cost_price,selling_price,vat_rate,stock_quantity,unit,image_filename,active\n"
             . "CUP-001,Ceramic Cup,Cups,Personalized ceramic cup,10,25,5,100,pcs,,true\n";

        $import = $service->commit($this->csvPath($csv), null, 1, isDryRun: false, extension: 'csv');

        $this->assertSame(1, $import->created_count);
        $product = Product::where('sku', 'CUP-001')->first();
        $this->assertNotNull($product);
        $this->assertSame('Ceramic Cup', $product->name_en);
        $this->assertEquals(25, $product->sale_price);
        $this->assertEquals(10, $product->cost_price);
    }

    public function test_csv_import_updates_existing_product_matched_by_sku(): void
    {
        $this->owner();
        Product::create(['sku' => 'CUP-001', 'name_en' => 'Old Name', 'sale_price' => 20, 'cost_price' => 8, 'unit' => 'piece', 'is_active' => true]);
        $service = app(ProductImportService::class);
        $csv = "sku,name,category,description,cost_price,selling_price,vat_rate,stock_quantity,unit,image_filename,active\n"
             . "CUP-001,Ceramic Cup Updated,Cups,,10,30,5,50,pcs,,true\n";

        $import = $service->commit($this->csvPath($csv), null, 1, isDryRun: false, extension: 'csv');

        $this->assertSame(0, $import->created_count);
        $this->assertSame(1, $import->updated_count);
        $this->assertSame(1, Product::count(), 'Existing SKU must update, never create a duplicate.');
        $product = Product::where('sku', 'CUP-001')->first();
        $this->assertSame('Ceramic Cup Updated', $product->name_en);
        $this->assertEquals(30, $product->sale_price);
    }

    public function test_csv_import_does_not_blank_out_an_existing_image_when_row_has_none(): void
    {
        Storage::fake('public');
        $this->owner();
        Storage::disk('public')->put('products/existing.jpg', 'fake-image-bytes');
        Product::create(['sku' => 'CUP-002', 'name_en' => 'Has Image', 'sale_price' => 15, 'cost_price' => 5, 'unit' => 'piece', 'is_active' => true, 'image_path' => 'products/existing.jpg']);

        $service = app(ProductImportService::class);
        $csv = "sku,name,category,description,cost_price,selling_price,vat_rate,stock_quantity,unit,image_filename,active\n"
             . "CUP-002,Has Image Updated,,,5,18,5,10,piece,,true\n"; // no image_filename in this row

        $service->commit($this->csvPath($csv), null, 1, isDryRun: false, extension: 'csv');

        $product = Product::where('sku', 'CUP-002')->first();
        $this->assertSame('products/existing.jpg', $product->image_path, 'A row with no image must never blank out an existing product image.');
    }

    public function test_only_owner_can_download_templates(): void
    {
        $this->seed(SystemDataSeeder::class);
        $sales = User::create(['name' => 'Sales', 'email' => 'sales-csv@example.test', 'password' => 'test-password', 'is_active' => true]);
        $sales->roles()->attach(Role::where('name', 'sales')->firstOrFail());

        $this->actingAs($sales)->get(route('products.import.csv-template'))->assertForbidden();
    }

    public function test_owner_can_download_csv_template(): void
    {
        $owner = $this->owner();
        $response = $this->actingAs($owner)->get(route('products.import.csv-template'));
        $response->assertOk();
        $response->assertSee('sku,name,category', false);
    }
}
