<?php

namespace Tests\Feature;

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_seven_dish_tags(): void
    {
        $this->seed(TagSeeder::class);

        $this->assertDatabaseCount('tags', 7);
        $this->assertDatabaseHas('tags', ['code' => 'popular', 'label' => 'Popular']);
        $this->assertDatabaseHas('tags', ['code' => 'spicy', 'label' => 'Spicy']);
    }

    public function test_tag_code_is_unique(): void
    {
        Tag::create(['code' => 'popular', 'label' => 'Popular']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tag::create(['code' => 'popular', 'label' => 'Popular duplicado']);
    }
}
