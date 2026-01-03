<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Season;
use App\Models\LeaderboardEntry;
use App\Models\Profile;

class LeaderboardController extends Controller
{
    /**
     * GET /api/leaderboard/current
     */
    public function currentSeason(Request $request)
    {
        $season = Season::where('is_active', true)->first();

        if (!$season) {
            return response()->json([
                'title'       => __('messages.leaderboard.off_season'),
                'message'     => __('messages.leaderboard.no_active_competition'),
                'leaderboard' => [],
                'user_stats'  => null
            ]);
        }

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
     * GET /api/leaderboard/all-time
     */
    public function allTime(Request $request)
    {
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

        $userStats = $this->getUserRank($request->user(), 'all_time');

        return response()->json([
            'title'       => 'All-Time',
            'type'        => 'all_time',
            'leaderboard' => $leaderboard,
            'user_stats'  => $userStats,
        ]);
    }

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
