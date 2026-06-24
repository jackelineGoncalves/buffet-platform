<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            ['code' => 'popular', 'label' => 'Popular'],
            ['code' => 'spicy', 'label' => 'Spicy'],
            ['code' => 'raw', 'label' => 'Raw'],
            ['code' => 'veg', 'label' => 'Vegetarian'],
            ['code' => 'gf', 'label' => 'Gluten-Free'],
            ['code' => 'chef', 'label' => "Chef's Choice"],
            ['code' => 'premium', 'label' => 'Premium'],
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }
    }
}
