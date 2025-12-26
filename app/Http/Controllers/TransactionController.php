<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecyclingBin;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    public function startSession(Request $request)
    {
        $timeLeft = 180;

        $request->validate([
            'qr_key'    => 'required|exists:recycling_bins,qr_key', //
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // Find bin by the key on the sticker
        $bin = RecyclingBin::where('qr_key', $request->qr_key)->firstOrFail();

        // Distance Check (Haversine Formula)
        $distance = $this->calculateDistance(
            $request->latitude,
            $request->longitude,
            $bin->latitude,
            $bin->longitude //
        );

        if ($distance > 20) {
            return response()->json(['message' => 'Too far from the bin.'], 403);
        }

        // Generate Session Token
        $token = Str::random(32);

        // Store Session: User ID + Bin ID
        Cache::put("recycle_session_{$token}", [
            'user_id' => $request->user()->id,
            'bin_id'  => $bin->id,
        ], $timeLeft); // 3 minutes

        return response()->json([
            'bin_name' => $bin->name,
            'session_token' => $token,
            'time_left' => $timeLeft,
        ]);
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
