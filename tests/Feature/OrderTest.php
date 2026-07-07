<?php

namespace Tests\Feature;

use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): DiningSession
    {
        $table = RestaurantTable::create(['code' => 'T' . uniqid(), 'seats' => 4]);

        return DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
    }

    public function test_order_belongs_to_a_dining_session(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->assertTrue($order->diningSession->is($session));
    }

    public function test_order_status_defaults_to_new(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->assertEquals('new', $order->status);
    }

    public function test_order_number_is_unique_within_a_dining_session(): void
    {
        $session = $this->makeSession();

        Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 2,
            'placed_at' => now(),
        ]);
    }

    public function test_order_number_can_repeat_across_different_dining_sessions(): void
    {
        $session1 = $this->makeSession();
        $session2 = $this->makeSession();

        Order::create([
            'dining_session_id' => $session1->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $order = Order::create([
            'dining_session_id' => $session2->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->assertTrue($order->exists);
    }

    public function test_sync_status_from_items_is_new_when_there_are_no_items(): void
    {
        $session = $this->makeSession();
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $order->syncStatusFromItems();

        $this->assertEquals('new', $order->status);
    }

    public function test_sync_status_from_items_is_prep_while_any_item_is_firing(): void
    {
        $session = $this->makeSession();
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        OrderItem::create(['order_id' => $order->id, 'name' => 'Roll', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'Nigiri', 'station_id' => $station->id, 'qty' => 1, 'status' => 'firing']);

        $order->syncStatusFromItems();

        $this->assertEquals('prep', $order->status);
    }

    public function test_sync_status_from_items_is_ready_when_none_are_firing_but_not_all_served(): void
    {
        $session = $this->makeSession();
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        OrderItem::create(['order_id' => $order->id, 'name' => 'Roll', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'Nigiri', 'station_id' => $station->id, 'qty' => 1, 'status' => 'served']);

        $order->syncStatusFromItems();

        $this->assertEquals('ready', $order->status);
    }

    public function test_sync_status_from_items_is_served_when_all_items_are_served(): void
    {
        $session = $this->makeSession();
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        OrderItem::create(['order_id' => $order->id, 'name' => 'Roll', 'station_id' => $station->id, 'qty' => 1, 'status' => 'served']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'Nigiri', 'station_id' => $station->id, 'qty' => 1, 'status' => 'served']);

        $order->syncStatusFromItems();

        $this->assertEquals('served', $order->status);
    }

    public function test_sync_status_from_items_is_prep_when_any_item_is_in_prep(): void
    {
        $session = $this->makeSession();
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#fff']);

        OrderItem::create(['order_id' => $order->id, 'name' => 'Chicken', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'Steak', 'station_id' => $station->id, 'qty' => 1, 'status' => 'prep']);

        $order->syncStatusFromItems();

        $this->assertEquals('prep', $order->fresh()->status);
    }
}
