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

        $profile = $user->profile()->create($validated);

        return response()->json($profile, 201);
    }

    /**
     * Update the authenticated user's profile.
     * Only username and bio are updatable.
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

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        return response()->json($profile, 200);
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
        ]);

        $profile->update($validated);

        return response()->json($profile, 200);
    }
}
