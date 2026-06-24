<?php

namespace Tests\Feature;

use App\Models\Station;
use Database\Seeders\StationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_five_kitchen_stations(): void
    {
        $this->seed(StationSeeder::class);

        $this->assertDatabaseCount('stations', 5);
        $this->assertDatabaseHas('stations', ['code' => 'sushi', 'label' => 'Sushi']);
        $this->assertDatabaseHas('stations', ['code' => 'bar', 'label' => 'Bar']);
    }

    public function test_station_code_is_unique(): void
    {
        Station::create(['code' => 'sushi', 'label' => 'Sushi', 'short_label' => 'SU', 'color' => '#fff']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Station::create(['code' => 'sushi', 'label' => 'Sushi duplicado', 'short_label' => 'SU', 'color' => '#000']);
    }
}
