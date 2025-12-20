<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_profile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/profile', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Bio test',
            'birth_date' => '1990-01-01',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'first_name' => 'John',
                'username' => 'johndoe',
            ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'username' => 'johndoe',
        ]);
    }

    public function test_user_cannot_create_duplicate_profile()
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Bio test',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->postJson('/api/profile', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'bio' => 'New bio',
            'birth_date' => '1995-01-01',
        ]);

        $response->assertStatus(409)
            ->assertJson(['message' => 'User already has a profile.']);
    }

    public function test_user_can_update_profile_username_and_bio_only()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Old bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'username' => 'newusername',
            'bio' => 'New bio',
            'first_name' => 'Jane', // Should be ignored or fail validation depending on strictness, our controller ignores non-validated
            'birth_date' => '2000-01-01', // Should be ignored
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'username' => 'newusername',
                'bio' => 'New bio',
                'first_name' => 'John', // Original value
                'birth_date' => '1990-01-01',
            ]);

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'username' => 'newusername',
            'bio' => 'New bio',
            'first_name' => 'John',
            'birth_date' => '1990-01-01', // DB format
        ]);
    }

    public function test_user_can_update_profile_partially_username_only()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Old bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'username' => 'newusername',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'username' => 'newusername',
                'bio' => 'Old bio', // Unchanged
            ]);
    }

    public function test_user_can_update_profile_partially_bio_only()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Old bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'bio' => 'New bio',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'username' => 'johndoe', // Unchanged
                'bio' => 'New bio',
            ]);
    }

    public function test_user_can_view_profile_by_id()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Bio test',
            'birth_date' => '1990-01-01',
        ]);

        $viewer = User::factory()->create(); // Another user viewing

        $response = $this->actingAs($viewer)->getJson('/api/profile/' . $profile->id);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $profile->id,
                'username' => 'johndoe',
            ]);
    }

    public function test_user_can_view_profile_by_username()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Bio test',
            'birth_date' => '1990-01-01',
        ]);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->getJson('/api/profile/username/johndoe');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $profile->id,
                'first_name' => 'John',
            ]);
    }

    public function test_guest_cannot_access_profile_routes()
    {
        $response = $this->getJson('/api/profile/1');
        $response->assertStatus(401);

        $response = $this->postJson('/api/profile', []);
        $response->assertStatus(401);
    }
    public function test_user_can_view_own_profile()
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Me',
            'last_name' => 'Myself',
            'username' => 'myself',
            'bio' => 'My bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/me');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $profile->id,
                'username' => 'myself',
            ]);
    }

    public function test_admin_can_update_any_profile_field()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Old bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($admin)->putJson('/api/admin/profile/johndoe', [
            'first_name' => 'Jane', // Admin updating restricted field
            'bio' => 'Admin updated bio',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'first_name' => 'Jane',
                'bio' => 'Admin updated bio',
                'last_name' => 'Doe', // Unchanged
                'username' => 'johndoe', // Unchanged
            ]);

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'first_name' => 'Jane',
            'bio' => 'Admin updated bio',
            'last_name' => 'Doe',
        ]);
    }

    public function test_normal_user_cannot_access_admin_update()
    {
        $user = User::factory()->create(['role' => 'user']);
        $targetUser = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $targetUser->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'bio' => 'Old bio',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->putJson('/api/admin/profile/johndoe', [
            'first_name' => 'Hacker',
        ]);

        $response->assertStatus(403);
    }
}
