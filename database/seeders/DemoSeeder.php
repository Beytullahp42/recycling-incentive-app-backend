<?php

namespace Database\Seeders;

use App\Enums\SessionLifecycle;
use App\Enums\TransactionStatus;
use App\Models\LeaderboardEntry;
use App\Models\Profile;
use App\Models\RecyclableItem;
use App\Models\RecyclableItemCategory;
use App\Models\RecyclingBin;
use App\Models\RecyclingSession;
use App\Models\Season;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::firstOrCreate(['email' => 'demo@recycling.example'], [
                'password' => config('demo.user_password'),
                'role' => 'user',
            ]);

            $profile = Profile::firstOrCreate(['user_id' => $user->id], [
                'first_name' => 'Deniz',
                'last_name' => 'Demo',
                'username' => 'recycling_demo',
                'bio' => 'Demo account for the recycling incentive app.',
                'birth_date' => '2000-04-22',
                'points' => 0,
                'balance' => 0,
            ]);

            $category = RecyclableItemCategory::firstOrCreate(['name' => 'Demo Plastic'], [
                'value' => 100,
            ]);

            $bottle = RecyclableItem::withTrashed()->firstOrCreate(['barcode' => '2000000000015'], [
                'name' => 'Demo 500 ml Plastic Bottle',
                'description' => 'Uses the category point value.',
                'category_id' => $category->id,
                'manual_value' => null,
            ]);
            $largeBottle = RecyclableItem::withTrashed()->firstOrCreate(['barcode' => '2000000000022'], [
                'name' => 'Demo 1.5 L Plastic Bottle',
                'description' => 'Overrides the category value with 150 points.',
                'category_id' => $category->id,
                'manual_value' => 150,
            ]);

            $bin = RecyclingBin::withTrashed()->firstOrCreate(['name' => 'Demo Konya Recycling Bin'], [
                'latitude' => random_int(3786500000, 3787500000) / 100000000,
                'longitude' => random_int(3248000000, 3249000000) / 100000000,
            ]);

            if (RecyclingSession::where('session_token', 'demo-recycling-session-000000001')->exists()) {
                return;
            }

            $season = Season::where('is_active', true)->first();
            if (! $season) {
                $season = Season::create([
                    'name' => now()->format('F Y'),
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                    'is_active' => true,
                ]);
            }

            $startedAt = now()->subMinutes(10);
            $session = new RecyclingSession([
                'user_id' => $user->id,
                'recycling_bin_id' => $bin->id,
                'session_token' => 'demo-recycling-session-000000001',
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addSeconds(220),
                'ended_at' => $startedAt->copy()->addSeconds(120),
                'lifecycle_status' => SessionLifecycle::CLOSED,
                'audit_status' => TransactionStatus::ACCEPTED,
                'proof_photo_path' => null,
            ]);
            $session->created_at = $startedAt;
            $session->updated_at = $session->ended_at;
            $session->save();

            $totalPoints = 0;
            foreach ([$bottle, $largeBottle] as $index => $item) {
                $transaction = new Transaction([
                    'user_id' => $user->id,
                    'recycling_session_id' => $session->id,
                    'recyclable_item_id' => $item->id,
                    'barcode' => $item->barcode,
                    'points_awarded' => $item->current_value,
                    'status' => TransactionStatus::ACCEPTED,
                ]);
                $transaction->created_at = $startedAt->copy()->addSeconds(30 * ($index + 1));
                $transaction->updated_at = $transaction->created_at;
                $transaction->save();
                $totalPoints += $transaction->points_awarded;
            }

            $profile->increment('points', $totalPoints);
            $profile->increment('balance', $totalPoints);
            LeaderboardEntry::firstOrCreate([
                'season_id' => $season->id,
                'user_id' => $user->id,
            ], ['points' => 0])->increment('points', $totalPoints);
        });
    }
}
