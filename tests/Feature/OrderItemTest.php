<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        return Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
    }

    public function test_order_item_belongs_to_an_order(): void
    {
        $order = $this->makeOrder();
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Salmon Nigiri',
            'station_id' => $station->id,
            'qty' => 2,
        ]);

        $this->assertTrue($item->order->is($order));
    }

    public function test_order_item_keeps_snapshot_when_dish_is_deleted(): void
    {
        $order = $this->makeOrder();
        $category = Category::create(['code' => 'nigiri', 'label' => 'Nigiri', 'sort_order' => 1]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);
        $dish = Dish::create([
            'name' => 'Salmon Nigiri',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'is_available' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'station_id' => $station->id,
            'qty' => 1,
        ]);

        $dish->delete();
        $item->refresh();

        $this->assertNull($item->dish_id);
        $this->assertEquals('Salmon Nigiri', $item->name);
    }

    public function test_order_item_unit_price_is_nullable_for_buffet_items(): void
    {
        $order = $this->makeOrder();
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Salmon Nigiri',
            'station_id' => $station->id,
            'qty' => 1,
        ]);

        $this->assertNull($item->unit_price);
    }

    public function test_order_item_status_defaults_to_firing(): void
    {
        $order = $this->makeOrder();
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Salmon Nigiri',
            'station_id' => $station->id,
            'qty' => 1,
        ]);

        $this->assertEquals('firing', $item->status);
    }

    public function test_order_has_many_order_items(): void
    {
        $order = $this->makeOrder();
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Salmon Nigiri',
            'station_id' => $station->id,
            'qty' => 1,
        ]);

        $this->assertCount(1, $order->orderItems);
    }
}
