<?php

namespace Tests\Feature;

use Tests\TestCase;

class DinerTableRouteTest extends TestCase
{
    public function test_guest_can_view_table_page_without_authentication(): void
    {
        $response = $this->get('/table/T1');

        $response->assertOk();
    }
}
