<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecyclingSession;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\DB;
use App\Models\Profile;

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
                'message' => 'Session not found',
            ], 404);
        }

        $session->load('transactions.item'); //what happens if I do this? this also returns its category too lol

        return response()->json($session);
    }

    public function setStatus(Request $request, $id)
    {

        // "accepted", "rejected"

        $request->validate([
            'status' => ['required', 'in:' . TransactionStatus::ACCEPTED->value . ',' . TransactionStatus::REJECTED->value],
        ]);

        $session = RecyclingSession::with('transactions')->findOrFail($id);

        if ($session->audit_status !== TransactionStatus::FLAGGED) {
            return response()->json([
                'message' => 'Session is not flagged',
            ], 400);
        }

        DB::transaction(function () use ($session, $request) {
            $newStatus = TransactionStatus::from($request->status);

            // 1. Update Session Status
            $session->audit_status = $newStatus;
            $session->save();

            // 2. Update Transactions
            // We only update 'flagged' transactions. We don't touch ones that were already valid.
            $flaggedTransactions = $session->transactions()
                ->where('status', TransactionStatus::FLAGGED)
                ->get();

            $pointsToAward = 0;

            foreach ($flaggedTransactions as $t) {
                $t->status = $newStatus;
                $t->save();
                $pointsToAward += $t->points_awarded;
            }

            // 3. If Approved, Add Points to User Profile
            if ($newStatus === TransactionStatus::ACCEPTED && $pointsToAward > 0) {
                // Find profile by user_id
                $profile = Profile::where('user_id', $session->user_id)->first();
                if ($profile) {
                    $profile->increment('points', $pointsToAward);
                }
            }
        });

        return response()->json([
            'message' => 'Session status updated successfully',
        ], 200);
    }
}
