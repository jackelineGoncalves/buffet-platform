<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_kitchen_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_kitchen_can_access_kitchen_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($user)->get('/kitchen')->assertOk();
    }

    public function test_floor_can_access_floor_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'floor']);

        $this->actingAs($user)->get('/floor')->assertOk();
    }

    public function test_floor_cannot_access_kitchen_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'floor']);

        $this->actingAs($user)->get('/kitchen')->assertForbidden();
    }
}
