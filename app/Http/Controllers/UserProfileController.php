<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function getProfile()
    {
        $user = Auth::user();

        $profile = UserProfile::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => $profile,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'designation' => 'nullable|string|max:255',
            'institution' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'bio' => 'nullable|string',

            'research_interests' => 'nullable',
            'skills' => 'nullable',

            'personal_website_url' => 'nullable|url|max:255',
            'orcid_id' => 'nullable|string|max:255',

            'publications' => 'nullable',
        ]);

        $profile = UserProfile::firstOrNew([
            'user_id' => $user->id,
        ]);

        if ($request->hasFile('photo')) {
            if ($profile->photo && Storage::disk('public')->exists($profile->photo)) {
                Storage::disk('public')->delete($profile->photo);
            }

            $validated['photo'] = $request->file('photo')->store('profiles', 'public');
        }

        $validated['research_interests'] = $this->normalizeJsonField(
            $request->research_interests
        );

        $validated['skills'] = $this->normalizeJsonField(
            $request->skills
        );

        $validated['publications'] = $this->normalizeJsonField(
            $request->publications
        );

        $profile->fill($validated);
        $profile->user_id = $user->id;
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile' => $profile,
        ]);
    }

    private function normalizeJsonField($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return array_filter(array_map('trim', explode(',', $value)));
    }
}
