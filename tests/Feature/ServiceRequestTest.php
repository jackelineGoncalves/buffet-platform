<?php

namespace Tests\Feature;

use App\Models\DiningSession;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): DiningSession
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        return DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
    }

    public function test_service_request_belongs_to_a_dining_session(): void
    {
        $session = $this->makeSession();

        $request = ServiceRequest::create([
            'dining_session_id' => $session->id,
            'type' => 'bill',
            'requested_at' => now(),
        ]);

        $this->assertTrue($request->diningSession->is($session));
    }

    public function test_service_request_resolved_at_is_nullable_until_resolved(): void
    {
        $session = $this->makeSession();

        $request = ServiceRequest::create([
            'dining_session_id' => $session->id,
            'type' => 'server',
            'requested_at' => now(),
        ]);

        $this->assertNull($request->resolved_at);
    }

    public function test_dining_session_has_many_service_requests(): void
    {
        $session = $this->makeSession();

        ServiceRequest::create([
            'dining_session_id' => $session->id,
            'type' => 'bill',
            'requested_at' => now(),
        ]);

        $this->assertCount(1, $session->serviceRequests);
    }
}
