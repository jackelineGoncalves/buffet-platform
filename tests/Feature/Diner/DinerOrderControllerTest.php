<?php

namespace Tests\Feature\Diner;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Dish;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeDish(array $overrides = []): Dish
    {
        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);

        return Dish::create(array_merge([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
            'is_available' => true,
        ], $overrides));
    }

    private function makeActiveSession(): DiningSession
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        return DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
    }

    public function test_places_an_order_against_the_active_session(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();
        $allergen = Allergen::create(['code' => 'nuts', 'label' => 'Contains nuts']);

        $response = $this->postJson('/table/T1/orders', [
            'items' => [
                ['dish_id' => $dish->id, 'qty' => 2, 'allergen_ids' => [$allergen->id]],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('order_items', ['name' => 'Grilled Chicken', 'qty' => 2]);
        $this->assertDatabaseHas('order_item_allergens', ['label' => 'Contains nuts']);
    }

    public function test_round_increments_across_successive_submissions(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();

        $this->postJson('/table/T1/orders', ['items' => [['dish_id' => $dish->id, 'qty' => 1]]]);
        $this->postJson('/table/T1/orders', ['items' => [['dish_id' => $dish->id, 'qty' => 1]]]);

        $this->assertDatabaseHas('orders', ['round' => 1]);
        $this->assertDatabaseHas('orders', ['round' => 2]);
    }

    public function test_returns_409_when_there_is_no_active_session(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $dish = $this->makeDish();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertStatus(409);
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $dish = $this->makeDish();

        $response = $this->postJson('/table/UNKNOWN/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertNotFound();
    }

    public function test_rejects_an_unavailable_dish(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish(['is_available' => false]);

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.dish_id']);
    }

    public function test_rejects_a_nonexistent_dish_id(): void
    {
        $this->makeActiveSession();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => 999999, 'qty' => 1]],
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_a_nonexistent_allergen_id(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1, 'allergen_ids' => [999999]]],
        ]);

        $response->assertStatus(422);
    }
}
