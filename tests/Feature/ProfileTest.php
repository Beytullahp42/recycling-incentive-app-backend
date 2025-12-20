<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_a_profile()
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

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'username' => 'johndoe',
        ]);

        $this->assertEquals($user->id, $profile->user->id);
        $this->assertEquals($profile->id, $user->profile->id);
    }

    public function test_profile_username_must_be_unique()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Profile::create([
            'user_id' => $user1->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'uniqueuser',
            'bio' => 'Bio test',
            'birth_date' => '1990-01-01',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Profile::create([
            'user_id' => $user2->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'uniqueuser', // Duplicate username
            'bio' => 'Bio test',
            'birth_date' => '1995-01-01',
        ]);
    }
}
