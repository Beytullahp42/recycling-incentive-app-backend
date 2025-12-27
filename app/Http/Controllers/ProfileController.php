<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Create a new profile for the authenticated user.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->profile) {
            return response()->json(['message' => 'User already has a profile.'], 409);
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
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $validated = $request->validate([
            'username' => 'sometimes|string|max:255|unique:profiles,username,' . $profile->id,
            'bio' => 'sometimes|nullable|string',
            // Do NOT add points here. Users cannot edit their own points.
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

        // This will now include "points": 0 (or whatever value) automatically
        return response()->json([
            'profile' => $profile,
        ], 200);
    }

    /**
     * Get profile by ID.
     */
    public function show($id)
    {
        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
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
            return response()->json(['message' => 'Profile not found.'], 404);
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
            return response()->json(['message' => 'Profile not found.'], 404);
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
