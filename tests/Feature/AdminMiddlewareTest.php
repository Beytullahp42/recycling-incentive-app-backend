<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_routes()
    {
        // Define a temporary route for testing
        \Illuminate\Support\Facades\Route::get('/admin/test', function () {
            return 'Admin Access';
        })->middleware('auth:sanctum', 'isAdmin');

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/test');

        $response->assertStatus(200)
            ->assertSee('Admin Access');
    }

    public function test_user_cannot_access_admin_routes()
    {
        // Define a temporary route for testing
        \Illuminate\Support\Facades\Route::get('/admin/test', function () {
            return 'Admin Access';
        })->middleware('auth:sanctum', 'isAdmin');

        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin/test');

        $response->assertStatus(403);
    }
}
