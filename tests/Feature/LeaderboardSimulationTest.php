<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;
use App\Models\RecyclableItem;
use App\Models\RecyclingBin;
use App\Models\Season;
use App\Models\RecyclingSession;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Enums\SessionLifecycle;
use Illuminate\Support\Facades\Cache;

class LeaderboardSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_logic_across_seasons()
    {
        // ==========================================
        // 0. SETUP
        // ==========================================
        $this->withoutExceptionHandling();
        echo "\n\n🚀 STARTING LEADERBOARD SIMULATION TEST 🚀\n";
        // FORCE the first season to current date for predictable naming
        $currentMonthName = now()->format('F Y');

        // Create Users
        $alice = User::factory()->create();
        Profile::create([
            'user_id' => $alice->id,
            'username' => 'Alice',
            'points' => 0,
            'balance' => 0,
            'first_name' => 'Alice',
            'last_name' => 'Wonderland',
            'birth_date' => '1990-01-01'
        ]);

        $bob = User::factory()->create();
        Profile::create([
            'user_id' => $bob->id,
            'username' => 'Bob',
            'points' => 0,
            'balance' => 0,
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'birth_date' => '1990-01-01'
        ]);

        $charlie = User::factory()->create();
        Profile::create([
            'user_id' => $charlie->id,
            'username' => 'Charlie',
            'points' => 0,
            'balance' => 0,
            'first_name' => 'Charlie',
            'last_name' => 'Chaplin',
            'birth_date' => '1990-01-01'
        ]);

        // Create Category
        $category = \App\Models\RecyclableItemCategory::create([
            'name' => 'General',
            'value' => 1
        ]);

        // Create Items
        // We create distinct items to avoid "Duplicate Item" logic which prevents points
        $plastic1 = RecyclableItem::create([
            'name' => 'Plastic Bottle 1',
            'barcode' => '111111',
            'description' => 'A plastic bottle',
            'manual_value' => 10,
            'category_id' => $category->id
        ]);

        $plastic2 = RecyclableItem::create([
            'name' => 'Plastic Bottle 2',
            'barcode' => '111112',
            'description' => 'A plastic bottle',
            'manual_value' => 10,
            'category_id' => $category->id
        ]);

        $plastic3 = RecyclableItem::create([
            'name' => 'Plastic Bottle 3',
            'barcode' => '111113',
            'description' => 'A plastic bottle',
            'manual_value' => 10,
            'category_id' => $category->id
        ]);

        $glass1 = RecyclableItem::create([
            'name' => 'Glass Bottle 1',
            'barcode' => '222222',
            'description' => 'A glass bottle',
            'manual_value' => 20,
            'category_id' => $category->id
        ]);

        $glass2 = RecyclableItem::create([
            'name' => 'Glass Bottle 2',
            'barcode' => '222223',
            'description' => 'A glass bottle',
            'manual_value' => 20,
            'category_id' => $category->id
        ]);

        // Create Bin
        $bin = RecyclingBin::create([
            'name' => 'Test Bin',
            'qr_key' => 'test_bin_key',
            'latitude' => 10.0,
            'longitude' => 10.0,
            'location_name' => 'Test Loc',
            'status' => 'active'
        ]);

        // Ensure a season starts if not already
        $activeSeason = Season::where('is_active', true)->first();
        if (!$activeSeason) {
            Artisan::call('app:rotate-seasons');
            $activeSeason = Season::where('is_active', true)->first();
        }
        echo "🌱 SEASON 1 STARTED: {$activeSeason->name}\n";


        // ==========================================
        // 1. SEASON 1 TRANSACTIONS
        // ==========================================
        echo "\nProcessing Season 1 Transactions...\n";

        // Alice: 3 Plastics = 30 pts
        $this->simulateTransaction($alice, $bin, [$plastic1, $plastic2, $plastic3]);
        // Bob: 1 Glass = 20 pts
        $this->simulateTransaction($bob, $bin, [$glass1]);
        // Charlie: 0 pts

        echo "✅ Transactions Complete.\n";

        // Verify Season 1 Leaderboard
        echo "\n-------- RAW JSON OUTPUT (Season 1) --------\n";
        echo "Logged in as: Alice\n";
        $response = $this->actingAs($alice)->getJson('/api/leaderboard/current-season');
        $data = $response->json();
        // $this->printLeaderboard($data['leaderboard']);
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "--------------------------------------------\n";

        $this->assertEquals('Alice', $data['leaderboard'][0]['username']);
        $this->assertEquals(30, $data['leaderboard'][0]['points']);
        $this->assertEquals('Bob', $data['leaderboard'][1]['username']);
        $this->assertEquals(20, $data['leaderboard'][1]['points']);

        // ==========================================
        // 2. ROTATE SEASON
        // ==========================================
        echo "\n🔄 ROTATING SEASON...\n";
        Artisan::call('app:rotate-seasons', ['--force' => true]);

        $newSeason = Season::where('is_active', true)->first();
        $this->assertNotEquals($activeSeason->id, $newSeason->id);

        // Next month
        $expectedNextSeasonName = now()->addMonth()->format('F Y');

        echo "🌱 SEASON 2 STARTED: {$newSeason->name}\n";


        // ==========================================
        // 3. SEASON 2 TRANSACTIONS
        // ==========================================
        echo "\nProcessing Season 2 Transactions...\n";

        // Bob: 2 Glass = 40 pts
        $this->simulateTransaction($bob, $bin, [$glass1, $glass2]);
        // Charlie: 1 Plastic = 10 pts
        $this->simulateTransaction($charlie, $bin, [$plastic1]);
        // Alice: 0 pts

        echo "✅ Transactions Complete.\n";


        // ==========================================
        // 4. VERIFY LEADERBOARDS
        // ==========================================

        // A. Current Season (Season 2)
        // Expected: Bob (40), Charlie (10), Alice (0/Not shown)
        echo "\n-------- RAW JSON OUTPUT (Season 2) --------\n";
        echo "Logged in as: Alice\n";
        $response = $this->actingAs($alice)->getJson('/api/leaderboard/current-season');
        $data = $response->json();
        // $this->printLeaderboard($data['leaderboard']);
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "--------------------------------------------\n";

        $this->assertEquals('Bob', $data['leaderboard'][0]['username']);
        $this->assertEquals(40, $data['leaderboard'][0]['points']);
        $this->assertEquals('Charlie', $data['leaderboard'][1]['username']);
        $this->assertEquals(10, $data['leaderboard'][1]['points']);


        // B. All-Time
        // Expected: 
        // Bob: 20 (S1) + 40 (S2) = 60
        // Alice: 30 (S1) + 0 (S2) = 30
        // Charlie: 0 (S1) + 10 (S2) = 10
        echo "\n-------- RAW JSON OUTPUT (All Time) --------\n";
        echo "Logged in as: Alice\n";

        // Refresh Alice to get updated accumulated points from DB
        $alice->refresh();

        $response = $this->actingAs($alice)->getJson('/api/leaderboard/all-time');
        $data = $response->json();
        // $this->printLeaderboard($data['leaderboard']);
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "--------------------------------------------\n";

        $this->assertEquals('Bob', $data['leaderboard'][0]['username']);
        $this->assertEquals(60, $data['leaderboard'][0]['points']);
        $this->assertEquals('Alice', $data['leaderboard'][1]['username']);
        $this->assertEquals(30, $data['leaderboard'][1]['points']);
        // ... existing assertions ...
        $this->assertEquals('Charlie', $data['leaderboard'][2]['username']);
        $this->assertEquals(10, $data['leaderboard'][2]['points']);

        // ==========================================
        // 4.5. VERIFY DASHBOARD (Season 2)
        // ==========================================
        echo "\n-------- RAW DASHBOARD OUTPUT (Alice - S2 End) --------\n";
        // Alice: 30 pts (S1), 0 pts (S2). Total 30. Rank S2: - (No entry). Total Items: 3
        $response = $this->actingAs($alice)->getJson('/api/dashboard');
        $d = $response->json();
        echo json_encode($d, JSON_PRETTY_PRINT) . "\n";
        $this->assertEquals(30, $d['score']);
        // We verify items count if logic assumes Transaction = Item. 
        // Alice recycled 3 items in S1.

        echo "\n-------- RAW DASHBOARD OUTPUT (Bob - S2 End) --------\n";
        // Bob: 60 pts total. Rank S2: 1. Total Items: 1 (S1) + 2 (S2) = 3
        $bob->refresh();
        $response = $this->actingAs($bob)->getJson('/api/dashboard');
        $d = $response->json();
        echo json_encode($d, JSON_PRETTY_PRINT) . "\n";
        $this->assertEquals(60, $d['score']);
        $this->assertEquals(1, $d['rank']);
        $this->assertNull($d['rival']);

        echo "\n-------- RAW DASHBOARD OUTPUT (Charlie - S2 End) --------\n";
        // Charlie: 10 pts total. Rank S2: 2. Total Items: 1
        $charlie->refresh();
        $response = $this->actingAs($charlie)->getJson('/api/dashboard');
        $d = $response->json();
        echo json_encode($d, JSON_PRETTY_PRINT) . "\n";
        $this->assertEquals(10, $d['score']);
        $this->assertEquals(2, $d['rank']);
        $this->assertNotNull($d['rival']);
        $this->assertEquals('Bob', $d['rival']['username']);
        $this->assertEquals(30, $d['rival']['gap']); // Bob 40 - Charlie 10 = 30 gap logic? 
        // Wait, rival gap: Rival points - My points. 40 - 10 = 30. Correct.

        // ==========================================
        // 5. INACTIVE USER SCENARIO (Dave)
        // ==========================================
        echo "\n-------- RAW JSON OUTPUT (Dave - Inactive) --------\n";
        $dave = User::factory()->create();
        Profile::create([
            'user_id' => $dave->id,
            'username' => 'Dave',
            'points' => 0,
            'balance' => 0,
            'first_name' => 'Dave',
            'last_name' => 'Doe',
            'birth_date' => '1990-01-01'
        ]);

        echo "Logged in as: Dave (No Transactions)\n";
        $response = $this->actingAs($dave)->getJson('/api/leaderboard/current-season');
        $data = $response->json();

        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "---------------------------------------------------\n";

        $this->assertEquals('-', $data['user_stats']['rank']);
        $this->assertEquals(0, $data['user_stats']['points']);


        // ==========================================
        // 6. OFF-SEASON SCENARIO
        // ==========================================
        echo "\n-------- RAW JSON OUTPUT (Off Season) --------\n";

        // Forcefully close the current season
        Season::where('is_active', true)->update(['is_active' => false]);
        echo "Active Season Manually Closed.\n";

        echo "Logged in as: Alice\n";
        $response = $this->actingAs($alice)->getJson('/api/leaderboard/current-season');
        $data = $response->json();

        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "----------------------------------------------\n";

        $this->assertEquals('Off Season', $data['title']);
        $this->assertEquals([], $data['leaderboard']);
        $this->assertNull($data['user_stats']);

        echo "\n🎉 TEST COMPLETED SUCCESSFULLY 🎉\n";
    }

    private function simulateTransaction($user, $bin, $items)
    {
        // 1. Manually create session to bypass API complexity for test speed
        // BUT we need to make sure we hit the Controller logic for POINTS logic?
        // Actually, the user wants "make those users submit some items". 
        // Calling the API /submit-item is the most authentic way.

        // Start Session (Mocked request or just manual setup?)
        // Let's manually setup the session state to save time, but call submit-item API

        $token = 'token_' . $user->id . '_' . uniqid();
        $session = RecyclingSession::create([
            'user_id' => $user->id,
            'recycling_bin_id' => $bin->id,
            'session_token' => $token,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'lifecycle_status' => SessionLifecycle::ACTIVE,
            'audit_status' => TransactionStatus::ACCEPTED,
        ]);

        // Seed Cache (simulating what startSession does)
        Cache::put("recycle_session_{$token}", [
            'db_id'      => $session->id,
            'user_id'    => $user->id,
            'profile_id' => $user->profile->id,
            'bin_id'     => $bin->id,
            'has_proof'  => false,
        ], 600);

        foreach ($items as $item) {
            $this->actingAs($user)->postJson('/api/submit-item', [
                'session_token' => $token,
                'barcode' => $item->barcode
            ]);
        }
    }

    private function printLeaderboard($leaderboard)
    {
        echo "Rank | User      | Points \n";
        echo "-----|-----------|--------\n";
        foreach ($leaderboard as $entry) {
            printf("%-4d | %-9s | %d\n", $entry['rank'], $entry['username'], $entry['points']);
        }
    }
}
