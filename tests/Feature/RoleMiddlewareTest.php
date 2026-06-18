<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin'])->get('/__test/admin-only', function () {
            return 'ok';
        });

        Route::middleware(['web', 'auth', 'role:kitchen,floor'])->get('/__test/kitchen-or-floor', function () {
            return 'ok';
        });
    }

    public function test_user_with_matching_role_can_access_route(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/__test/admin-only');

        $response->assertOk();
    }

    public function test_user_with_wrong_role_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'kitchen']);

        $response = $this->actingAs($user)->get('/__test/admin-only');

        $response->assertForbidden();
    }

    public function test_user_matching_one_of_multiple_allowed_roles_can_access(): void
    {
        $user = User::factory()->create(['role' => 'floor']);

        $response = $this->actingAs($user)->get('/__test/kitchen-or-floor');

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/__test/admin-only');

        $response->assertRedirect(route('login'));
    }
}
