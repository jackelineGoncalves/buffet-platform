<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerTableRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_table_page_without_authentication(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->get('/table/T1');

        $response->assertOk();
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $response = $this->get('/table/UNKNOWN');

        $response->assertNotFound();
    }
}
