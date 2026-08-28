<?php
namespace Tests\Feature;
use App\Models\{Role,User};
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class InstallationTest extends TestCase
{
    use RefreshDatabase;
    public function test_first_owner_setup_is_one_time_only():void
    {
        $this->seed(SystemDataSeeder::class);
        $this->get('/setup')->assertOk()->assertSee('Create the first Owner');
        $this->post('/setup',['name'=>'Ivory Owner','email'=>'owner@ivory.test','password'=>'VerySecurePassword123!','password_confirmation'=>'VerySecurePassword123!'])->assertRedirect('/');
        $owner=User::where('email','owner@ivory.test')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));
        $this->get('/setup')->assertNotFound();
    }
}
