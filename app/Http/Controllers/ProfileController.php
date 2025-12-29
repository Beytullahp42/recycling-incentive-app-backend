<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Season;
use App\Models\LeaderboardEntry;
use App\Enums\TransactionStatus;

class ProfileController extends Controller
{
    /**
     * Create a new profile for the authenticated user.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->profile) {
            return response()->json(['message' => __('messages.profile.already_exists')], 409);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:profiles,username',
            'bio' => 'nullable|string',
            'birth_date' => 'required|date',
        ]);

        // Points will default to 0 via the database default value
        $profile = $user->profile()->create($validated);

        return response()->json($profile, 201);
    }

    /**
     * Update the authenticated user's profile.
     * Only username and bio are updatable.
     * STRICTLY PREVENT POINTS UPDATE HERE.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return response()->json(['message' => __('messages.profile.not_found')], 404);
        }

        $validated = $request->validate([
            'username' => 'sometimes|string|max:255|unique:profiles,username,' . $profile->id,
            'bio' => 'sometimes|nullable|string',
        ]);

        $profile->update($validated);

        return response()->json($profile, 200);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request)
    {
        $profile = $request->user()->profile;

        return response()->json([
            'profile' => $profile,
        ], 200);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        // 1. Basic Stats
        $points = $user->profile->points;

        // 2. Total Items Recycled (Simple Count)
        $totalItems = Transaction::where('user_id', $user->id)
            ->where('status', TransactionStatus::ACCEPTED)
            ->count();

        // 3. Leaderboard Logic (Rank & Rival)
        $rank = '-';
        $rival = null;

        $activeSeason = Season::where('is_active', true)->first();

        if ($activeSeason) {
            // A. Find My Entry for this Season
            $myEntry = LeaderboardEntry::where('season_id', $activeSeason->id)
                ->where('user_id', $user->id)
                ->first();

            if ($myEntry) {
                // Calculate Rank: Count how many people have MORE points than me
                $rank = LeaderboardEntry::where('season_id', $activeSeason->id)
                    ->where('points', '>', $myEntry->points)
                    ->count() + 1;

                // B. Find the "Rival" (The person directly above me)
                $rivalEntry = LeaderboardEntry::with('user.profile')
                    ->where('season_id', $activeSeason->id)
                    ->where('points', '>', $myEntry->points)
                    ->orderBy('points', 'asc') // Smallest gap first
                    ->first();

                if ($rivalEntry) {
                    $rival = [
                        'username' => $rivalEntry->user->profile->username,
                        'gap'      => $rivalEntry->points - $myEntry->points
                    ];
                }
            }
        }

        return response()->json([
            'score'       => $points,
            'total_items' => $totalItems,
            'rank'        => $rank,
            'rival'       => $rival, // null if I am #1 or unranked
        ]);
    }

    /**
     * Get profile by ID.
     */
    public function show($id)
    {
        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['message' => __('messages.profile.not_found')], 404);
        }

        return response()->json($profile, 200);
    }

    /**
     * Get profile by username.
     */
    public function showByUsername($username)
    {
        $profile = Profile::where('username', $username)->first();

        if (! $profile) {
            return response()->json(['message' => __('messages.profile.not_found')], 404);
        }

        return response()->json($profile, 200);
    }

    /**
     * Update any profile field (Admin only).
     * ADDED: Ability to update points manually.
     */
    public function adminUpdate(Request $request, $username)
    {
        $profile = Profile::where('username', $username)->first();

        if (! $profile) {
            return response()->json(['message' => __('messages.profile.not_found')], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:profiles,username,' . $profile->id,
            'bio' => 'sometimes|nullable|string',
            'birth_date' => 'sometimes|date',
            'points' => 'sometimes|integer|min:0', // <--- Allowed for Admins
        ]);

        $profile->update($validated);

        return response()->json($profile, 200);
    }
}
