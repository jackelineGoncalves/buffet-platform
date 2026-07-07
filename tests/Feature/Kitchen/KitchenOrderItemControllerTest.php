<?php

namespace Tests\Feature\Kitchen;

use App\Events\OrderItemStatusUpdated;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KitchenOrderItemControllerTest extends TestCase
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

    private function makeOrderItem(array $overrides = []): OrderItem
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

        return OrderItem::create(array_merge([
            'order_id' => $order->id,
            'name' => 'Grilled Chicken',
            'station_id' => $station->id,
            'qty' => 1,
            'status' => 'firing',
        ], $overrides));
    }

    public function test_advances_firing_item_to_prep(): void
    {
        $item = $this->makeOrderItem(['status' => 'firing']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('prep', $item->fresh()->status);
    }

    public function test_advances_prep_item_to_ready(): void
    {
        $item = $this->makeOrderItem(['status' => 'prep']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('ready', $item->fresh()->status);
    }

    public function test_advances_ready_item_to_served(): void
    {
        $item = $this->makeOrderItem(['status' => 'ready']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('served', $item->fresh()->status);
    }

    public function test_syncs_order_status_after_advancing(): void
    {
        $item = $this->makeOrderItem(['status' => 'firing']);

        $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $this->assertSame('prep', $item->order->fresh()->status);
    }

    public function test_returns_422_for_already_served_item(): void
    {
        $item = $this->makeOrderItem(['status' => 'served']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertStatus(422);
        $this->assertSame('served', $item->fresh()->status);
    }

    public function test_non_kitchen_role_receives_403(): void
    {
        $item = $this->makeOrderItem();
        $floor = User::create([
            'name' => 'Floor',
            'email' => 'floor@example.com',
            'password' => bcrypt('password'),
            'role' => 'floor',
        ]);

        $response = $this->actingAs($floor)
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_dispatches_order_item_status_updated_event(): void
    {
        Event::fake([OrderItemStatusUpdated::class]);
        $item = $this->makeOrderItem(['status' => 'firing']);

        $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        Event::assertDispatched(OrderItemStatusUpdated::class, function ($event) use ($item) {
            return $event->orderItem->id === $item->id
                && $event->orderItem->status === 'prep';
        });
    }
}
