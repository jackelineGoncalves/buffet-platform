<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Station;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all()->keyBy('code');
        $stations = Station::all()->keyBy('code');

        $dishes = [
            ['name' => 'Salmon Nigiri', 'category' => 'nigiri', 'station' => 'sushi', 'price' => 0, 'per_round_limit' => 4, 'is_extra' => false],
            ['name' => 'Tuna Nigiri', 'category' => 'nigiri', 'station' => 'sushi', 'price' => 0, 'per_round_limit' => 4, 'is_extra' => false],
            ['name' => 'Premium Toro Nigiri', 'category' => 'nigiri', 'station' => 'sushi', 'price' => 4.50, 'per_round_limit' => null, 'is_extra' => true],
            ['name' => 'California Maki', 'category' => 'maki', 'station' => 'sushi', 'price' => 0, 'per_round_limit' => 3, 'is_extra' => false],
            ['name' => 'Dragon Roll', 'category' => 'maki', 'station' => 'sushi', 'price' => 6.00, 'per_round_limit' => null, 'is_extra' => true],
            ['name' => 'Salmon Sashimi', 'category' => 'sashimi', 'station' => 'cold', 'price' => 0, 'per_round_limit' => 3, 'is_extra' => false],
            ['name' => 'Beef Teriyaki Wok', 'category' => 'wok', 'station' => 'hot', 'price' => 0, 'per_round_limit' => 2, 'is_extra' => false],
            ['name' => 'Vegetable Tempura', 'category' => 'wok', 'station' => 'fry', 'price' => 0, 'per_round_limit' => 2, 'is_extra' => false],
            ['name' => 'Miso Soup', 'category' => 'soup', 'station' => 'hot', 'price' => 0, 'per_round_limit' => null, 'is_extra' => false],
            ['name' => 'Mochi Ice Cream', 'category' => 'dessert', 'station' => 'cold', 'price' => 0, 'per_round_limit' => 2, 'is_extra' => false],
            ['name' => 'Craft Soda', 'category' => 'dessert', 'station' => 'bar', 'price' => 3.00, 'per_round_limit' => null, 'is_extra' => true],
        ];

        foreach ($dishes as $dish) {
            Dish::create([
                'name' => $dish['name'],
                'category_id' => $categories[$dish['category']]->id,
                'station_id' => $stations[$dish['station']]->id,
                'price' => $dish['price'],
                'per_round_limit' => $dish['per_round_limit'],
                'is_available' => true,
                'is_extra' => $dish['is_extra'],
            ]);
        }
    }
}
