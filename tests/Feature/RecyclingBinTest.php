<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\RecyclingBin;

class RecyclingBinTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bin_with_auto_key()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/recycling-bins', [
            'name' => 'Central Park Bin',
            'latitude' => 40.785091,
            'longitude' => -73.968285,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('recycling_bins', [
            'name' => 'Central Park Bin',
            'latitude' => 40.785091,
        ]);

        $bin = \App\Models\RecyclingBin::where('name', 'Central Park Bin')->first();
        $this->assertNotNull($bin->qr_key);
        $this->assertEquals(16, strlen($bin->qr_key));
    }

    public function test_user_cannot_create_bin()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->postJson('/api/recycling-bins', [
            'name' => 'Illegal Bin',
            'latitude' => 0,
            'longitude' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_only_admin_can_view_bins()
    {
        \App\Models\RecyclingBin::create([
            'name' => 'Public Bin',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/recycling-bins')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Public Bin']);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/recycling-bins')
            ->assertStatus(403);

        $this->getJson('/api/recycling-bins')
            ->assertStatus(403);
    }

    public function test_admin_can_update_bin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bin = \App\Models\RecyclingBin::create([
            'name' => 'Old Name',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);

        $originalKey = $bin->qr_key;

        $response = $this->actingAs($admin)->putJson('/api/recycling-bins/' . $bin->id, [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);

        $bin->refresh();
        $this->assertEquals('New Name', $bin->name);
        $this->assertEquals($originalKey, $bin->qr_key); // Key should not change
    }

    public function test_soft_delete()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bin = \App\Models\RecyclingBin::create([
            'name' => 'To Delete',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/recycling-bins/' . $bin->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('recycling_bins', ['id' => $bin->id]);
    }
}
