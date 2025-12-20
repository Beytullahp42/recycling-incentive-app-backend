<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User; // Added for User model usage

class AdminAuthTest extends TestCase
{
    use RefreshDatabase; // Moved here for correct placement

    public function test_admin_can_login_via_spa_route()
    {
        $admin = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ], ['Referer' => 'http://localhost:3000']);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged in successfully']);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_user_cannot_login_via_spa_route()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ], ['Referer' => 'http://localhost:3000']);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);

        $this->assertGuest();
    }

    public function test_admin_can_logout_via_spa_route()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'web');

        $response = $this->postJson('/api/admin/logout', [], ['Referer' => 'http://localhost:3000']);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertGuest('web');
    }
}
