<?php

namespace Tests\Feature;

use App\Exceptions\TableOccupiedException;
use App\Models\Allergen;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Dish;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiningSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dining_session_belongs_to_a_restaurant_table(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertTrue($session->restaurantTable->is($table));
    }

    public function test_dining_session_status_defaults_to_seated(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertEquals('seated', $session->status);
    }

    public function test_dining_session_waste_count_defaults_to_zero(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertSame(0, $session->waste_count);
    }

    public function test_dining_session_closed_at_is_nullable(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertNull($session->closed_at);
    }

    public function test_dining_session_has_many_orders(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->assertCount(1, $session->orders);
    }

    public function test_cannot_open_a_second_active_session_on_an_occupied_table(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->expectException(TableOccupiedException::class);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);
    }

    public function test_database_constraint_blocks_a_second_active_session_bypassing_the_model_check(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Bypasses the Eloquent `creating` event entirely, so only the database's
        // partial unique index can stop this from creating a second active session.
        DB::table('dining_sessions')->insert([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'status' => 'seated',
            'waste_count' => 0,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_open_a_new_session_after_the_previous_one_is_closed(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $first = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
        $first->update(['status' => 'closed', 'closed_at' => now()]);

        $second = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);

        $this->assertTrue($second->exists);
    }

    public function test_place_order_creates_an_order_with_snapshotted_items(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
        ]);
        $allergen = Allergen::create(['code' => 'nuts', 'label' => 'Contains nuts']);

        $order = $session->placeOrder([
            [
                'dish_id' => $dish->id,
                'qty' => 2,
                'note' => 'No salt',
                'allergen_ids' => [$allergen->id],
            ],
        ]);

        $this->assertSame(1, $order->round);
        $this->assertSame(1, $order->number);
        $this->assertSame($session->id, $order->dining_session_id);

        $item = $order->orderItems->first();
        $this->assertSame('Grilled Chicken', $item->name);
        $this->assertEquals(12.50, $item->unit_price);
        $this->assertSame(2, $item->qty);
        $this->assertSame('No salt', $item->note);
        $this->assertSame($station->id, $item->station_id);
        $this->assertSame('firing', $item->status);

        $itemAllergen = $item->orderItemAllergens->first();
        $this->assertSame('Contains nuts', $itemAllergen->label);
        $this->assertSame($allergen->id, $itemAllergen->allergen_id);
    }

    public function test_place_order_increments_round_and_number_on_successive_calls(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
        ]);

        $first = $session->placeOrder([['dish_id' => $dish->id, 'qty' => 1]]);
        $second = $session->placeOrder([['dish_id' => $dish->id, 'qty' => 1]]);

        $this->assertSame(1, $first->round);
        $this->assertSame(2, $second->round);
        $this->assertSame(2, $second->number);
    }
}
