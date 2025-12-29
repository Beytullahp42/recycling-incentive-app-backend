<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Season;
use App\Models\LeaderboardEntry;
use App\Models\Profile;

class LeaderboardController extends Controller
{
    /**
     * Endpoint 1: GET /api/leaderboard/current
     */
    public function currentSeason(Request $request)
    {
        // 1. Find Active Season
        $season = Season::where('is_active', true)->first();

        if (!$season) {
            return response()->json([
                'title'       => __('messages.leaderboard.off_season'),
                'message'     => __('messages.leaderboard.no_active_competition'),
                'leaderboard' => [],
                'user_stats'  => null
            ]);
        }

        // 2. Top 50 for THIS Season
        $leaderboard = LeaderboardEntry::with('user.profile')
            ->where('season_id', $season->id)
            ->orderByDesc('points')
            ->limit(50)
            ->get()
            ->map(function ($entry, $index) {
                return [
                    'rank'     => $index + 1,
                    'username' => $entry->user->profile?->username,
                    'points'   => $entry->points,
                ];
            });

        // 3. User's Seasonal Rank
        $userStats = $this->getUserRank($request->user(), 'season', $season->id);

        return response()->json([
            'title'       => $season->name,
            'type'        => 'season',
            'starts_at'   => $season->starts_at,
            'ends_at'     => $season->ends_at,
            'month_number' => $season->starts_at->month,
            'year'         => $season->starts_at->year,
            'leaderboard' => $leaderboard,
            'user_stats'  => $userStats,
        ]);
    }

    /**
     * Endpoint 2: GET /api/leaderboard/all-time
     */
    public function allTime(Request $request)
    {
        // 1. Top 50 Global Users
        $leaderboard = Profile::with('user')
            ->orderByDesc('points')
            ->limit(50)
            ->get()
            ->map(function ($profile, $index) {
                return [
                    'rank'     => $index + 1,
                    'username' => $profile->username,
                    'points'   => $profile->points,
                ];
            });

        // 2. User's All-Time Rank
        $userStats = $this->getUserRank($request->user(), 'all_time');

        return response()->json([
            'title'       => 'All-Time',
            'type'        => 'all_time',
            'leaderboard' => $leaderboard,
            'user_stats'  => $userStats,
        ]);
    }

    /**
     * Shared Helper: Calculates rank efficiently
     */
    private function getUserRank($user, $type, $seasonId = null)
    {
        if (!$user || !$user->profile) return null;

        $rank = '-';
        $points = 0;

        if ($type === 'all_time') {
            $points = $user->profile->points;
            if ($points > 0) {
                $count = Profile::where('points', '>', $points)->count();
                $rank = $count + 1;
            }
        } else {
            // Seasonal
            $entry = LeaderboardEntry::where('season_id', $seasonId)
                ->where('user_id', $user->id)
                ->first();

            if ($entry) {
                $points = $entry->points;
                if ($points > 0) {
                    $count = LeaderboardEntry::where('season_id', $seasonId)
                        ->where('points', '>', $points)
                        ->count();
                    $rank = $count + 1;
                }
            }
        }

        return ['rank' => $rank, 'username' => $user->profile?->username, 'points' => $points];
    }
}
