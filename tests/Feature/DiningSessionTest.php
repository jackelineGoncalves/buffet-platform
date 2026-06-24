<?php

namespace Tests\Feature;

use App\Exceptions\TableOccupiedException;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiningSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dining_session_belongs_to_a_restaurant_table(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertTrue($session->restaurantTable->is($table));
    }

    public function test_dining_session_status_defaults_to_seated(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertEquals('seated', $session->status);
    }

    public function test_dining_session_waste_count_defaults_to_zero(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertSame(0, $session->waste_count);
    }

    public function test_dining_session_closed_at_is_nullable(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->assertNull($session->closed_at);
    }

    public function test_dining_session_has_many_orders(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        $this->assertCount(1, $session->orders);
    }

    public function test_cannot_open_a_second_active_session_on_an_occupied_table(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->expectException(TableOccupiedException::class);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);
    }

    public function test_database_constraint_blocks_a_second_active_session_bypassing_the_model_check(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Bypasses the Eloquent `creating` event entirely, so only the database's
        // partial unique index can stop this from creating a second active session.
        DB::table('dining_sessions')->insert([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'status' => 'seated',
            'waste_count' => 0,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_open_a_new_session_after_the_previous_one_is_closed(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $first = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
        $first->update(['status' => 'closed', 'closed_at' => now()]);

        $second = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);

        $this->assertTrue($second->exists);
    }
}
