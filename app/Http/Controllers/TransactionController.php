<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecyclingBin;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\RecyclingSession;
use App\Models\RecyclableItem;
use App\Models\Profile;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Enums\SessionLifecycle;

class TransactionController extends Controller
{
    public function startSession(Request $request)
    {
        // 1. Pre-check: User MUST have a profile to collect points
        if (!$request->user()->profile) {
            return response()->json(['message' => 'Please create a profile first.'], 403);
        }

        $clientDuration = 180;
        $gracePeriod    = 40;
        $serverDuration = $clientDuration + $gracePeriod;

        $request->validate([
            'qr_key'    => 'required|exists:recycling_bins,qr_key',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $bin = RecyclingBin::where('qr_key', $request->qr_key)->firstOrFail();

        $distance = $this->calculateDistance(
            $request->latitude,
            $request->longitude,
            $bin->latitude,
            $bin->longitude
        );

        if ($distance > 20) {
            return response()->json(['message' => 'Too far from the bin.'], 403);
        }

        do {
            $token = Str::random(32);
        } while (RecyclingSession::where('session_token', $token)->exists());

        // --- NEW LIFECYCLE LOGIC ---
        $session = RecyclingSession::create([
            'user_id'          => $request->user()->id,
            'recycling_bin_id' => $bin->id,
            'session_token'    => $token,
            'started_at'       => now(),
            'expires_at'       => now()->addSeconds($serverDuration),

            // Set initial statuses
            'lifecycle_status' => SessionLifecycle::ACTIVE,
            'audit_status'     => TransactionStatus::ACCEPTED,

            'proof_photo_path' => null,
            'ended_at'         => null,
        ]);

        Cache::put("recycle_session_{$token}", [
            'db_id'      => $session->id,
            'user_id'    => $request->user()->id,
            'profile_id' => $request->user()->profile->id,
            'bin_id'     => $bin->id,
            'has_proof'  => false,
        ], $serverDuration);

        return response()->json([
            'bin_name'      => $bin->name,
            'session_token' => $token,
            'time_left'     => $clientDuration,
        ]);
    }

    public function submitItem(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'barcode'       => 'required|string',
        ]);

        $token = $request->session_token;
        $cachedSession = Cache::get("recycle_session_{$token}");

        // Fallback if cache is missing (server restart or memory clear)
        if (! $cachedSession) {
            $session = RecyclingSession::where('session_token', $token)
                ->where('lifecycle_status', SessionLifecycle::ACTIVE) // Only active sessions
                ->where('expires_at', '>', now())
                ->first();

            if (! $session) {
                return response()->json(['message' => 'Session expired or invalid.'], 403);
            }

            $cachedSession = [
                'db_id'      => $session->id,
                'user_id'    => $session->user_id,
                'profile_id' => $session->user->profile->id,
                'bin_id'     => $session->recycling_bin_id,
                'has_proof'  => ! is_null($session->proof_photo_path),
            ];

            $remaining = $session->expires_at->diffInSeconds(now());
            if ($remaining > 0) Cache::put("recycle_session_{$token}", $cachedSession, $remaining);
        }

        $item = RecyclableItem::where('barcode', $request->barcode)->first();

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Unknown item.'], 404);
        }

        // Check for duplicates in THIS session
        $existingCount = Transaction::where('recycling_session_id', $cachedSession['db_id'])
            ->where('barcode', $request->barcode)
            ->count();

        $snapshotPoints = $item->current_value;
        $status = TransactionStatus::ACCEPTED;

        if ($existingCount > 0) {
            if ($cachedSession['has_proof']) {
                // If they already uploaded a photo, we flag it but allow it
                $status = TransactionStatus::FLAGGED;
            } else {
                // Stop them and ask for a photo
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate item detected! Please take a group photo.',
                    'requires_proof' => true
                ], 422);
            }
        }

        Transaction::create([
            'user_id'              => $cachedSession['user_id'],
            'recycling_session_id' => $cachedSession['db_id'],
            'recyclable_item_id'   => $item->id,
            'barcode'              => $request->barcode,
            'points_awarded'       => $snapshotPoints,
            'status'               => $status,
        ]);

        // Only give points immediately if it is NOT flagged
        if ($status === TransactionStatus::ACCEPTED) {
            Profile::where('id', $cachedSession['profile_id'])
                ->increment('points', $snapshotPoints);
        }

        return response()->json([
            'success'        => true,
            'points_awarded' => ($status === TransactionStatus::ACCEPTED) ? $snapshotPoints : 0,
            'item_name'      => $item->name,
            'status'         => $status,
            'message'        => ($status === TransactionStatus::FLAGGED) ? 'Item saved for review.' : 'Points added!',
        ]);
    }

    /**
     * Upload Proof - Updates AUDIT STATUS only
     */
    public function uploadProof(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'proof_photo'   => 'required|image|max:10240',
        ]);

        $token = $request->session_token;

        $session = RecyclingSession::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Session expired or invalid.'], 403);
        }

        $path = $request->file('proof_photo')->store('proofs', 'public');

        // Update the audit status to FLAGGED (Session remains ACTIVE)
        $session->update([
            'proof_photo_path' => $path,
            'audit_status'     => TransactionStatus::FLAGGED,
        ]);

        // CRITICAL: Update Cache so submitItem knows proof exists
        if (Cache::has("recycle_session_{$token}")) {
            $data = Cache::get("recycle_session_{$token}");
            $data['has_proof'] = true;

            $remaining = $session->expires_at->diffInSeconds(now());
            Cache::put("recycle_session_{$token}", $data, $remaining);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proof uploaded successfully. Unlimited scanning unlocked.',
        ]);
    }

    /**
     * End Session Manually
     */
    public function endSession(Request $request)
    {
        $request->validate(['session_token' => 'required|string']);
        $token = $request->session_token;

        // 1. Kill the Cache immediately
        Cache::forget("recycle_session_{$token}");

        // 2. Mark DB as closed
        RecyclingSession::where('session_token', $token)->update([
            'lifecycle_status' => SessionLifecycle::CLOSED,
            'ended_at'         => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Session ended.']);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
