<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_registration_screen(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_registration_screen(): void
    {
        $user = User::factory()->create(['role' => 'kitchen']);

        $response = $this->actingAs($user)->get('/register');

        $response->assertForbidden();
    }

    public function test_admin_can_view_registration_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/register');

        $response->assertStatus(200);
    }

    public function test_admin_can_register_new_staff_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'kitchen',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'kitchen']);
        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('register'));
    }

    public function test_non_admin_cannot_register_new_staff_user(): void
    {
        $user = User::factory()->create(['role' => 'kitchen']);

        $response = $this->actingAs($user)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
