<?php

namespace Database\Seeders;

use App\Models\Allergen;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AllergenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allergens = [
            ['code' => 'shellfish', 'label' => 'Shellfish allergy'],
            ['code' => 'gluten', 'label' => 'Gluten-free'],
            ['code' => 'raw_fish', 'label' => 'No raw fish'],
            ['code' => 'nuts', 'label' => 'Nut allergy'],
            ['code' => 'dairy', 'label' => 'Dairy-free'],
            ['code' => 'soy', 'label' => 'Soy allergy'],
        ];

        foreach ($allergens as $allergen) {
            Allergen::create($allergen);
        }
    }
}
