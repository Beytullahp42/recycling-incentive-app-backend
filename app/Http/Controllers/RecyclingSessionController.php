<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecyclingSession;
use App\Enums\TransactionStatus;
use App\Models\LeaderboardEntry;
use Illuminate\Support\Facades\DB;
use App\Models\Profile;
use App\Models\Season;

class RecyclingSessionController extends Controller
{
    public function index()
    {
        $sessions = RecyclingSession::query()
            ->with('user.profile')
            ->with('bin')
            ->withSum('acceptedTransactions as accepted_points', 'points_awarded')
            ->withSum('flaggedTransactions as flagged_points', 'points_awarded')
            ->withSum('rejectedTransactions as rejected_points', 'points_awarded')
            ->orderByRaw("audit_status = ? DESC", [TransactionStatus::FLAGGED->value])
            ->orderBy('created_at', 'asc')
            ->withCount('transactions')
            ->paginate(15);

        return response()->json($sessions);
    }

    public function show($id)
    {
        $session = RecyclingSession::query()
            ->with('user.profile')
            ->with('bin')
            ->withSum('acceptedTransactions as accepted_points', 'points_awarded')
            ->withSum('flaggedTransactions as flagged_points', 'points_awarded')
            ->withSum('rejectedTransactions as rejected_points', 'points_awarded')
            ->find($id);

        if (!$session) {
            return response()->json([
                'message' => __('messages.session.not_found'),
            ], 404);
        }

        $session->load('transactions.item');

        return response()->json($session);
    }

    public function setStatus(Request $request, $id)
    {

        $request->validate([
            'status' => ['required', 'in:' . TransactionStatus::ACCEPTED->value . ',' . TransactionStatus::REJECTED->value],
        ]);

        $session = RecyclingSession::with('transactions')->findOrFail($id);

        if ($session->audit_status !== TransactionStatus::FLAGGED) {
            return response()->json([
                'message' => __('messages.session.not_flagged'),
            ], 400);
        }

        DB::transaction(function () use ($session, $request) {
            $newStatus = TransactionStatus::from($request->status);

            $session->audit_status = $newStatus;
            $session->save();
            $flaggedTransactions = $session->transactions()
                ->where('status', TransactionStatus::FLAGGED)
                ->get();

            $pointsToAward = 0;

            foreach ($flaggedTransactions as $t) {
                $t->status = $newStatus;
                $t->save();
                $pointsToAward += $t->points_awarded;
            }

            if ($newStatus === TransactionStatus::ACCEPTED && $pointsToAward > 0) {
                $profile = Profile::where('user_id', $session->user_id)->first();
                if ($profile) {
                    $profile->increment('points', $pointsToAward);
                    $profile->increment('balance', $pointsToAward);
                    $activeSeason = Season::where('is_active', true)->first();
                    if ($activeSeason) {
                        $entry = LeaderboardEntry::firstOrCreate(
                            [
                                'user_id' => $session->user_id,
                                'season_id' => $activeSeason->id
                            ],
                            [
                                'points' => 0
                            ]
                        );
                        $entry->increment('points', $pointsToAward);
                    }
                }
            }
        });

        return response()->json([
            'message' => __('messages.session.status_updated'),
        ], 200);
    }
}
