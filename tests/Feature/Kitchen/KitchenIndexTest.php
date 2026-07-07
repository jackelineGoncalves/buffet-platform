<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeKitchenUser(): User
    {
        return User::create([
            'name' => 'Chef',
            'email' => 'chef@example.com',
            'password' => bcrypt('password'),
            'role' => 'kitchen',
        ]);
    }

    public function test_kitchen_page_loads_for_kitchen_role(): void
    {
        $response = $this->actingAs($this->makeKitchenUser())->get('/kitchen');

        $response->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/kitchen');

        $response->assertRedirect('/login');
    }

    public function test_non_kitchen_role_receives_403(): void
    {
        $floor = User::create([
            'name' => 'Floor',
            'email' => 'floor@example.com',
            'password' => bcrypt('password'),
            'role' => 'floor',
        ]);

        $response = $this->actingAs($floor)->get('/kitchen');

        $response->assertForbidden();
    }

    public function test_only_active_items_are_included_in_initial_props(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'name' => 'A', 'station_id' => $station->id, 'qty' => 1, 'status' => 'firing']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'B', 'station_id' => $station->id, 'qty' => 1, 'status' => 'prep']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'C', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'D', 'station_id' => $station->id, 'qty' => 1, 'status' => 'served']);

        $response = $this->actingAs($this->makeKitchenUser())->get('/kitchen');

        $items = $response->viewData('page')['props']['initialItems'];
        $statuses = collect($items)->pluck('status');

        $this->assertContains('firing', $statuses);
        $this->assertContains('prep', $statuses);
        $this->assertContains('ready', $statuses);
        $this->assertNotContains('served', $statuses);
    }
}
