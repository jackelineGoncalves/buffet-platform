<?php

namespace Tests\Feature\Diner;

use App\Models\DiningSession;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opens_a_new_session_when_none_is_active(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->postJson('/table/T1/session', ['guests' => 3]);

        $response->assertCreated();
        $this->assertDatabaseHas('dining_sessions', [
            'guests' => 3,
            'status' => 'seated',
        ]);
    }

    public function test_returns_409_and_does_not_duplicate_when_a_session_is_already_active(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);

        $response = $this->postJson('/table/T1/session', ['guests' => 5]);

        $response->assertStatus(409);
        $this->assertSame(1, DiningSession::count());
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $response = $this->postJson('/table/UNKNOWN/session', ['guests' => 2]);

        $response->assertNotFound();
    }

    public function test_guests_is_required_and_must_be_a_positive_integer(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->postJson('/table/T1/session', ['guests' => 0]);

        $response->assertStatus(422);
    }
}
