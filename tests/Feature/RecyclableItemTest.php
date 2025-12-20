<?php

namespace Tests\Feature;

use App\Models\RecyclableItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RecyclableItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_item()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/recyclable-items', [
            'name' => 'Plastic Bottle',
            'description' => 'A standard plastic bottle',
            'value' => 10,
            'barcode' => '123456789',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Plastic Bottle',
                'value' => 10,
            ]);

        $this->assertDatabaseHas('recyclable_items', [
            'name' => 'Plastic Bottle',
            'barcode' => '123456789',
        ]);
    }

    public function test_user_cannot_create_item()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->postJson('/api/recyclable-items', [
            'name' => 'Glass Bottle',
            'barcode' => '987654321',
        ]);

        $response->assertStatus(403);
    }

    public function test_everyone_can_view_items()
    {
        RecyclableItem::create([
            'name' => 'Can',
            'barcode' => '111222333',
        ]);

        $user = User::factory()->create();

        // Admin view
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/recyclable-items')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Can']);

        // User view
        $this->actingAs($user)->getJson('/api/recyclable-items')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Can']);

        // Guest view
        $this->getJson('/api/recyclable-items')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Can']);
    }

    public function test_default_value_logic()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/recyclable-items', [
            'name' => 'Paper',
            'barcode' => '555666777',
            // value is missing
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('recyclable_items', [
            'name' => 'Paper',
            'value' => 5, // Default value
        ]);
    }

    public function test_soft_delete()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = RecyclableItem::create([
            'name' => 'Box',
            'barcode' => '999888777',
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/recyclable-items/' . $item->id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('recyclable_items', [
            'id' => $item->id,
        ]);
    }
}
