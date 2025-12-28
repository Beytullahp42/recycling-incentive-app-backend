<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Profile;
use App\Models\RecyclingBin;
use App\Models\RecyclableItem;
use App\Models\RecyclableItemCategory;
use App\Models\RecyclingSession;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Enums\SessionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a user with a profile.
     */
    private function createUserWithProfile(): User
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser' . $user->id,
            'bio' => 'Test bio',
            'birth_date' => '1990-01-01',
            'points' => 0,
        ]);
        return $user->fresh();
    }

    /**
     * Helper to create a bin at a specific location.
     */
    private function createBin(float $lat = 40.0, float $lon = 29.0): RecyclingBin
    {
        return RecyclingBin::factory()->atLocation($lat, $lon)->create();
    }

    /**
     * Helper to start a valid session for testing.
     */
    private function startValidSession(User $user, RecyclingBin $bin): string
    {
        $response = $this->actingAs($user)->postJson('/api/start-session', [
            'qr_key' => $bin->qr_key,
            'latitude' => $bin->latitude,
            'longitude' => $bin->longitude,
        ]);

        return $response->json('session_token');
    }

    // =====================================================
    // GUEST ACCESS TESTS
    // =====================================================

    public function test_guest_cannot_access_transaction_routes()
    {
        $response = $this->postJson('/api/start-session', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/submit-item', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/upload-proof', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/end-session', []);
        $response->assertStatus(401);
    }

    // =====================================================
    // START SESSION TESTS
    // =====================================================

    public function test_start_session_fails_without_profile()
    {
        $user = User::factory()->create(); // No profile
        $bin = $this->createBin();

        $response = $this->actingAs($user)->postJson('/api/start-session', [
            'qr_key' => $bin->qr_key,
            'latitude' => $bin->latitude,
            'longitude' => $bin->longitude,
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Please create a profile first.']);
    }

    public function test_start_session_fails_with_invalid_qr_key()
    {
        $user = $this->createUserWithProfile();

        $response = $this->actingAs($user)->postJson('/api/start-session', [
            'qr_key' => 'invalid_key',
            'latitude' => 40.0,
            'longitude' => 29.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_start_session_fails_when_too_far_from_bin()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin(40.0, 29.0);

        // User location is ~1km away (approx 0.01 degrees latitude = 1.1km)
        $response = $this->actingAs($user)->postJson('/api/start-session', [
            'qr_key' => $bin->qr_key,
            'latitude' => 40.01,
            'longitude' => 29.0,
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Too far from the bin.']);
    }

    public function test_start_session_succeeds_when_near_bin()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin(40.0, 29.0);

        $response = $this->actingAs($user)->postJson('/api/start-session', [
            'qr_key' => $bin->qr_key,
            'latitude' => 40.0,
            'longitude' => 29.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['bin_name', 'session_token', 'time_left']);

        $this->assertDatabaseHas('recycling_sessions', [
            'user_id' => $user->id,
            'recycling_bin_id' => $bin->id,
            'lifecycle_status' => SessionLifecycle::ACTIVE->value,
        ]);
    }

    // =====================================================
    // SUBMIT ITEM TESTS
    // =====================================================

    public function test_submit_item_fails_with_invalid_session()
    {
        $user = $this->createUserWithProfile();

        $response = $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => 'invalid_token',
            'barcode' => '1234567890123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Session expired or invalid.']);
    }

    public function test_submit_item_fails_for_unknown_barcode()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $sessionToken = $this->startValidSession($user, $bin);

        $response = $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => 'unknown_barcode',
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Unknown item.']);
    }

    public function test_submit_item_succeeds_and_awards_points()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $category = RecyclableItemCategory::factory()->withValue(10)->create();
        $item = RecyclableItem::factory()->forCategory($category)->create();

        $sessionToken = $this->startValidSession($user, $bin);

        $response = $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => $item->barcode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'points_awarded' => 10,
                'item_name' => $item->name,
                'message' => 'Points added!',
            ]);

        // Verify points were added to profile
        $this->assertEquals(10, $user->profile->fresh()->points);
    }

    public function test_submit_duplicate_item_requires_proof()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $item = RecyclableItem::factory()->withManualValue(5)->create();

        $sessionToken = $this->startValidSession($user, $bin);

        // First submission - should succeed
        $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => $item->barcode,
        ])->assertStatus(200);

        // Second submission of same barcode - should require proof
        $response = $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => $item->barcode,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Duplicate item detected! Please take a group photo.',
                'requires_proof' => true,
            ]);
    }

    public function test_submit_duplicate_item_succeeds_after_proof()
    {
        Storage::fake('public');

        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $item = RecyclableItem::factory()->withManualValue(5)->create();

        $sessionToken = $this->startValidSession($user, $bin);

        // First submission
        $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => $item->barcode,
        ])->assertStatus(200);

        // Upload proof
        $this->actingAs($user)->postJson('/api/upload-proof', [
            'session_token' => $sessionToken,
            'proof_photo' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ])->assertStatus(200);

        // Second submission after proof - should succeed with FLAGGED status
        $response = $this->actingAs($user)->postJson('/api/submit-item', [
            'session_token' => $sessionToken,
            'barcode' => $item->barcode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item saved for review.',
            ]);

        // Verify the transaction has FLAGGED status
        $this->assertDatabaseHas('transactions', [
            'barcode' => $item->barcode,
            'status' => TransactionStatus::FLAGGED->value,
        ]);
    }

    // =====================================================
    // UPLOAD PROOF TESTS
    // =====================================================

    public function test_upload_proof_fails_with_invalid_session()
    {
        Storage::fake('public');
        $user = $this->createUserWithProfile();

        $response = $this->actingAs($user)->postJson('/api/upload-proof', [
            'session_token' => 'invalid_token',
            'proof_photo' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Session expired or invalid.']);
    }

    public function test_upload_proof_succeeds()
    {
        Storage::fake('public');

        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $sessionToken = $this->startValidSession($user, $bin);

        $response = $this->actingAs($user)->postJson('/api/upload-proof', [
            'session_token' => $sessionToken,
            'proof_photo' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof uploaded successfully. Unlimited scanning unlocked.',
            ]);

        // Verify session has proof photo and FLAGGED audit status
        $session = RecyclingSession::where('session_token', $sessionToken)->first();
        $this->assertNotNull($session->proof_photo_path);
        $this->assertEquals(TransactionStatus::FLAGGED, $session->audit_status);
    }

    // =====================================================
    // END SESSION TESTS
    // =====================================================

    public function test_end_session_succeeds()
    {
        $user = $this->createUserWithProfile();
        $bin = $this->createBin();
        $sessionToken = $this->startValidSession($user, $bin);

        // Verify cache exists
        $this->assertTrue(Cache::has("recycle_session_{$sessionToken}"));

        $response = $this->actingAs($user)->postJson('/api/end-session', [
            'session_token' => $sessionToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Session ended.',
            ]);

        // Verify cache is cleared
        $this->assertFalse(Cache::has("recycle_session_{$sessionToken}"));

        // Verify session is marked as CLOSED
        $this->assertDatabaseHas('recycling_sessions', [
            'session_token' => $sessionToken,
            'lifecycle_status' => SessionLifecycle::CLOSED->value,
        ]);
    }
}
