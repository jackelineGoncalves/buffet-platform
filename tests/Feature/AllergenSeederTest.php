<?php

namespace Tests\Feature;

use App\Models\Allergen;
use Database\Seeders\AllergenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllergenSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_dietary_allergens(): void
    {
        $this->seed(AllergenSeeder::class);

        $this->assertDatabaseHas('allergens', ['code' => 'shellfish', 'label' => 'Shellfish allergy']);
        $this->assertDatabaseHas('allergens', ['code' => 'gluten', 'label' => 'Gluten-free']);
        $this->assertDatabaseHas('allergens', ['code' => 'raw_fish', 'label' => 'No raw fish']);
    }

    public function test_allergen_code_is_unique(): void
    {
        Allergen::create(['code' => 'shellfish', 'label' => 'Shellfish allergy']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Allergen::create(['code' => 'shellfish', 'label' => 'Shellfish allergy duplicado']);
    }
}
