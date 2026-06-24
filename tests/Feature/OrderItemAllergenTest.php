<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllergen;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemAllergenTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderItem(): OrderItem
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
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        return OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Shrimp Tempura',
            'station_id' => $station->id,
            'qty' => 1,
        ]);
    }

    public function test_order_item_allergen_belongs_to_an_order_item(): void
    {
        $item = $this->makeOrderItem();
        $allergen = Allergen::create(['code' => 'shellfish', 'label' => 'Shellfish allergy']);

        $orderItemAllergen = OrderItemAllergen::create([
            'order_item_id' => $item->id,
            'allergen_id' => $allergen->id,
            'label' => $allergen->label,
        ]);

        $this->assertTrue($orderItemAllergen->orderItem->is($item));
    }

    public function test_order_item_allergen_keeps_snapshot_when_allergen_is_deleted(): void
    {
        $item = $this->makeOrderItem();
        $allergen = Allergen::create(['code' => 'shellfish', 'label' => 'Shellfish allergy']);

        $orderItemAllergen = OrderItemAllergen::create([
            'order_item_id' => $item->id,
            'allergen_id' => $allergen->id,
            'label' => $allergen->label,
        ]);

        $allergen->delete();
        $orderItemAllergen->refresh();

        $this->assertNull($orderItemAllergen->allergen_id);
        $this->assertEquals('Shellfish allergy', $orderItemAllergen->label);
    }

    public function test_order_item_has_many_allergens(): void
    {
        $item = $this->makeOrderItem();
        $allergen = Allergen::create(['code' => 'shellfish', 'label' => 'Shellfish allergy']);

        OrderItemAllergen::create([
            'order_item_id' => $item->id,
            'allergen_id' => $allergen->id,
            'label' => $allergen->label,
        ]);

        $this->assertCount(1, $item->orderItemAllergens);
    }
}
