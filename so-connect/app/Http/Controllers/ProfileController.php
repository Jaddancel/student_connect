<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function profileForm()
    {
        return view('pages.profile.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'mname' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $payload = [
            'first_name' => $validated['fname'],
            'last_name' => $validated['lname'],
            'middle_name' => $validated['mname'] ?? '',
            'occupation' => $validated['occupation'] ?? 'Other',
            'address' => null,
        ];

        $existingProfile = $user?->profile ? Profile::find($user->profile) : null;

        if ($existingProfile) {
            $existingProfile->update($payload);
            $profileId = $existingProfile->getKey();
        } else {
            $profileId = Profile::create($payload)->getKey();
        }

        $user?->update([
            'profile' => $profileId,
        ]);

        return redirect()->route('dashboard');
    }
}
