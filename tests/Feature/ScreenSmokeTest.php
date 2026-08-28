<?php
namespace Tests\Feature;
use App\Models\{Role,User};
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ScreenSmokeTest extends TestCase
{
    use RefreshDatabase;
    public function test_owner_can_render_every_main_screen():void
    {
        $this->seed(SystemDataSeeder::class);$owner=User::factory()->create(['is_active'=>true]);$owner->roles()->attach(Role::where('name','owner')->first());$this->actingAs($owner);
        $paths=['/','/customers','/customers/create','/products','/products/create','/suppliers','/suppliers/create','/quotations','/quotations/create','/orders','/orders/create','/invoices','/production','/deliveries','/purchases','/expenses','/inventory','/accounting','/accounting/trial-balance','/reports','/users','/settings','/system/health','/system/audit','/system/import-export','/system/backups','/system/recovery-snapshot'];
        foreach($paths as $path)$this->get($path)->assertOk();
        $this->get('/sync/version')->assertOk()->assertJsonStructure(['version','server_time']);
    }
}
