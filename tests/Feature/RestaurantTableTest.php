<?php

namespace Tests\Feature;

use App\Models\DiningSession;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_table_code_is_unique(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        RestaurantTable::create(['code' => 'T1', 'seats' => 6]);
    }

    public function test_restaurant_table_has_many_dining_sessions(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertCount(1, $table->diningSessions);
    }
}
