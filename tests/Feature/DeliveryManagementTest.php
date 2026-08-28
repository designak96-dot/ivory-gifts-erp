<?php

namespace Tests\Feature;

use App\Models\{Customer, DeliveryNote, Role, SalesOrder, Setting, User};
use App\Services\DeliverySchedulingService;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_see_live_delivery_calendar_and_reports(): void
    {
        $this->seed(SystemDataSeeder::class);
        $owner = User::create(['name' => 'Delivery Test Owner', 'email' => 'delivery-owner@example.test', 'password' => 'test-password', 'is_active' => true]);
        $owner->roles()->attach(Role::where('name', 'owner')->firstOrFail());
        $this->actingAs($owner)->get('/deliveries')->assertOk()->assertSee('Delivery calendar')->assertSee('Updates automatically every 12 seconds')->assertSee('Delivery reports');
        $this->actingAs($owner)->get('/delivery-report')->assertOk()->assertSee('By driver')->assertSee('By emirate');
        $this->actingAs($owner)->get('/deliveries/live')->assertOk()->assertJsonStructure(['version', 'html']);
    }

    public function test_scheduler_moves_to_next_day_when_daily_limit_is_full(): void
    {
        $this->seed(SystemDataSeeder::class);
        Setting::where('key', 'delivery_limit_per_day')->update(['value' => 2]);
        $customer = Customer::create(['customer_code' => 'CUS-TEST', 'name' => 'Test Customer', 'status' => 'active']);
        $date = today()->addDays(3);
        foreach (range(1, 2) as $number) {
            $order = SalesOrder::create(['order_number' => "SO-TEST-{$number}", 'order_month' => today()->startOfMonth(), 'customer_id' => $customer->id, 'order_date' => today(), 'delivery_date' => $date]);
            DeliveryNote::create(['delivery_note_number' => "DN-TEST-{$number}", 'sales_order_id' => $order->id, 'customer_id' => $customer->id, 'delivery_date' => $date, 'status' => 'pending']);
        }
        $next = app(DeliverySchedulingService::class)->nextAvailableDate($date);
        $this->assertSame($date->copy()->addDay()->toDateString(), $next->toDateString());
    }
}
