<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Station;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DishTest extends TestCase
{
    use RefreshDatabase;

    public function test_dish_belongs_to_a_category_and_a_station(): void
    {
        $category = Category::create(['code' => 'nigiri', 'label' => 'Nigiri', 'sort_order' => 1]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $dish = Dish::create([
            'name' => 'Salmon Nigiri',
            'description' => 'Fresh salmon over rice',
            'prep_minutes' => 5,
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => null,
            'per_round_limit' => null,
            'is_available' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $this->assertTrue($dish->category->is($category));
        $this->assertTrue($dish->station->is($station));
    }

    public function test_dish_price_and_per_round_limit_are_nullable(): void
    {
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

        $this->assertNull($dish->price);
        $this->assertNull($dish->per_round_limit);
    }

    public function test_dish_can_have_an_add_on_price(): void
    {
        $category = Category::create(['code' => 'premium', 'label' => 'Premium', 'sort_order' => 1]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $dish = Dish::create([
            'name' => 'Wagyu Roll',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
            'is_available' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $this->assertEquals(12.50, $dish->price);
    }

    public function test_dish_belongs_to_many_tags(): void
    {
        $category = Category::create(['code' => 'maki', 'label' => 'Maki', 'sort_order' => 1]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $dish = Dish::create([
            'name' => 'Spicy Tuna Roll',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'is_available' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $spicy = Tag::create(['code' => 'spicy', 'label' => 'Spicy']);
        $popular = Tag::create(['code' => 'popular', 'label' => 'Popular']);

        $dish->tags()->attach([$spicy->id, $popular->id]);

        $this->assertCount(2, $dish->tags);
        $this->assertTrue($dish->tags->contains($spicy));
    }

    public function test_category_has_many_dishes(): void
    {
        $category = Category::create(['code' => 'nigiri', 'label' => 'Nigiri', 'sort_order' => 1]);
        $station = Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        Dish::create([
            'name' => 'Salmon Nigiri',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'is_available' => true,
            'is_custom' => false,
            'sort_order' => 1,
        ]);

        $this->assertCount(1, $category->dishes);
    }

    public function test_dish_is_extra_defaults_to_false(): void
    {
        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);

        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
        ]);

        $this->assertFalse($dish->is_extra);
    }

    public function test_dish_is_extra_can_be_set_true(): void
    {
        $category = Category::create(['code' => 'drinks', 'label' => 'Drinks', 'sort_order' => 2]);
        $station = Station::create(['code' => 'bar', 'label' => 'Bar', 'short_label' => 'BAR', 'color' => '#00ff00']);

        $dish = Dish::create([
            'name' => 'Craft Soda',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'is_extra' => true,
        ]);

        $this->assertTrue($dish->is_extra);
    }
}
