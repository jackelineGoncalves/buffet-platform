<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_menu_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', ['code' => 'nigiri', 'label' => 'Nigiri']);
        $this->assertDatabaseHas('categories', ['code' => 'maki', 'label' => 'Maki']);
    }

    public function test_category_code_is_unique(): void
    {
        Category::create(['code' => 'nigiri', 'label' => 'Nigiri', 'sort_order' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Category::create(['code' => 'nigiri', 'label' => 'Nigiri duplicado', 'sort_order' => 2]);
    }
}
