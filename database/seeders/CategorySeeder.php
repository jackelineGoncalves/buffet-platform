<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'nigiri', 'label' => 'Nigiri', 'sort_order' => 1],
            ['code' => 'maki', 'label' => 'Maki', 'sort_order' => 2],
            ['code' => 'sashimi', 'label' => 'Sashimi', 'sort_order' => 3],
            ['code' => 'wok', 'label' => 'Wok', 'sort_order' => 4],
            ['code' => 'soup', 'label' => 'Soup', 'sort_order' => 5],
            ['code' => 'dessert', 'label' => 'Dessert', 'sort_order' => 6],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
