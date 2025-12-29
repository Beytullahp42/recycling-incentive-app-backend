<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\RecyclingBin;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_invalid_login_translation()
    {
        // Test English
        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/login', [
                'email' => 'wrong@example.com',
                'password' => 'wrong',
            ]);
        $response->assertStatus(403)
            ->assertJson(['message' => 'Invalid login details']);

        // Test Turkish
        $response = $this->withHeaders(['Accept-Language' => 'tr'])
            ->postJson('/api/login', [
                'email' => 'wrong@example.com',
                'password' => 'wrong',
            ]);
        $response->assertStatus(403)
            ->assertJson(['message' => 'Giriş bilgileri geçersiz']);
    }

    public function test_bin_not_found_translation()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Test English
        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/recycling-bins/99999');
        $response->assertStatus(404)
            ->assertJson(['message' => 'Bin not found.']);

        // Test Turkish
        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'tr'])
            ->getJson('/api/recycling-bins/99999');
        $response->assertStatus(404)
            ->assertJson(['message' => 'Geri dönüşüm kutusu bulunamadı.']);
    }

    public function test_transaction_create_profile_first_translation()
    {
        $user = User::factory()->create(); // User without profile

        // Test English
        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/start-session', [
                'qr_key' => 'valid_key',
                'latitude' => 10,
                'longitude' => 10,
            ]);
        // Expecting 403 "Please create a profile first."
        $response->assertStatus(403)
            ->assertJson(['message' => 'Please create a profile first.']);

        // Test Turkish
        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'tr'])
            ->postJson('/api/start-session', [
                'qr_key' => 'valid_key',
                'latitude' => 10,
                'longitude' => 10,
            ]);
        $response->assertStatus(403)
            ->assertJson(['message' => 'Lütfen önce bir profil oluşturun.']);
    }
}
