<?php

namespace Tests\Feature;

use App\Models\RecyclableItem;
use App\Models\RecyclableItemCategory;
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
        $category = RecyclableItemCategory::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/recyclable-items', [
            'name' => 'Plastic Bottle',
            'description' => 'A standard plastic bottle',
            'manual_value' => 10,
            'barcode' => '123456789',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Plastic Bottle',
                'manual_value' => 10,
            ]);

        $this->assertDatabaseHas('recyclable_items', [
            'name' => 'Plastic Bottle',
            'barcode' => '123456789',
        ]);
    }

    public function test_user_cannot_create_item()
    {
        $user = User::factory()->create(['role' => 'user']);

        $category = RecyclableItemCategory::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/recyclable-items', [
            'name' => 'Glass Bottle',
            'barcode' => '987654321',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_only_admin_can_view_items()
    {
        $category = RecyclableItemCategory::factory()->create();
        RecyclableItem::create([
            'name' => 'Can',
            'barcode' => '111222333',
            'category_id' => $category->id,
        ]);

        $user = User::factory()->create();

        // Admin view -> 200
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/recyclable-items')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Can']);

        // User view -> 403
        $this->actingAs($user)->getJson('/api/recyclable-items')
            ->assertStatus(403);

        // Guest view -> 403 (handled by IsAdmin middleware checking !user)
        $this->getJson('/api/recyclable-items')
            ->assertStatus(403);
    }

    public function test_default_value_logic()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = RecyclableItemCategory::factory()->create(['value' => 5]);

        $response = $this->actingAs($admin)->postJson('/api/recyclable-items', [
            'name' => 'Paper',
            'barcode' => '555666777',
            'category_id' => $category->id,
            // manual_value is missing
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('recyclable_items', [
            'name' => 'Paper',
            'manual_value' => null, // Should be null in DB
        ]);

        // Ensure the API returns the calculated value (from category) if logic exists
        // But here we are just checking DB persistence as per original test intent, adapted
    }

    public function test_soft_delete()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = RecyclableItemCategory::factory()->create();
        $item = RecyclableItem::create([
            'name' => 'Box',
            'barcode' => '999888777',
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/recyclable-items/' . $item->id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('recyclable_items', [
            'id' => $item->id,
        ]);
    }
}
