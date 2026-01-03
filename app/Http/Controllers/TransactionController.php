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
use App\Models\LeaderboardEntry;
use App\Models\Season;

class TransactionController extends Controller
{
    public function startSession(Request $request)
    {
        if (!$request->user()->profile) {
            return response()->json(['message' => __('messages.transaction.create_profile_first')], 403);
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
            return response()->json(['message' => __('messages.transaction.too_far')], 403);
        }

        do {
            $token = Str::random(32);
        } while (RecyclingSession::where('session_token', $token)->exists());

        $session = RecyclingSession::create([
            'user_id'          => $request->user()->id,
            'recycling_bin_id' => $bin->id,
            'session_token'    => $token,
            'started_at'       => now(),
            'expires_at'       => now()->addSeconds($serverDuration),

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

        if (! $cachedSession) {
            $session = RecyclingSession::where('session_token', $token)
                ->where('lifecycle_status', SessionLifecycle::ACTIVE)
                ->where('expires_at', '>', now())
                ->first();

            if (! $session) {
                return response()->json(['message' => __('messages.transaction.session_expired')], 403);
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
            return response()->json(['success' => false, 'message' => __('messages.transaction.unknown_item')], 404);
        }

        $existingCount = Transaction::where('recycling_session_id', $cachedSession['db_id'])
            ->where('barcode', $request->barcode)
            ->count();

        $snapshotPoints = $item->current_value;
        $status = TransactionStatus::ACCEPTED;

        if ($existingCount > 0) {
            if ($cachedSession['has_proof']) {
                $status = TransactionStatus::FLAGGED;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.transaction.duplicate_item'),
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

        if ($status === TransactionStatus::ACCEPTED) {
            Profile::where('id', $cachedSession['profile_id'])
                ->increment('points', $snapshotPoints);
            Profile::where('id', $cachedSession['profile_id'])
                ->increment('balance', $snapshotPoints);
            $activeSeason = Season::where('is_active', true)->first();
            if ($activeSeason) {
                $entry = LeaderboardEntry::firstOrCreate(
                    [
                        'user_id' => $cachedSession['user_id'],
                        'season_id' => $activeSeason->id
                    ],
                    [
                        'points' => 0
                    ]
                );
                $entry->increment('points', $snapshotPoints);
            }
        }

        return response()->json([
            'success'        => true,
            'points_awarded' => ($status === TransactionStatus::ACCEPTED) ? $snapshotPoints : 0,
            'item_name'      => $item->name,
            'status'         => $status,
            'message'        => ($status === TransactionStatus::FLAGGED) ? __('messages.transaction.item_flagged') : __('messages.transaction.points_added'),
        ]);
    }

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
            return response()->json(['message' => __('messages.transaction.session_expired')], 403);
        }

        $path = $request->file('proof_photo')->store('proofs', 'public');

        $session->update([
            'proof_photo_path' => $path,
            'audit_status'     => TransactionStatus::FLAGGED,
        ]);

        if (Cache::has("recycle_session_{$token}")) {
            $data = Cache::get("recycle_session_{$token}");
            $data['has_proof'] = true;

            $remaining = $session->expires_at->diffInSeconds(now());
            Cache::put("recycle_session_{$token}", $data, $remaining);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.transaction.proof_uploaded'),
        ]);
    }

    public function endSession(Request $request)
    {
        $request->validate(['session_token' => 'required|string']);
        $token = $request->session_token;

        Cache::forget("recycle_session_{$token}");

        RecyclingSession::where('session_token', $token)->update([
            'lifecycle_status' => SessionLifecycle::CLOSED,
            'ended_at'         => now(),
        ]);

        return response()->json(['success' => true, 'message' => __('messages.transaction.session_ended')]);
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
