<?php

namespace App\Http\Controllers;

use App\Helpers\ProfileMatchHelper;
use App\Models\Approval;
use App\Models\Request as ActionRequest;
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
        ]);

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ((int) ($user->profile ?? 0) > 0) {
            return redirect()->route('dashboard');
        }

        $hasPendingProfileRequest = ActionRequest::query()
            ->where('action_type', 9)
            ->where('user', (int) $user->getKey())
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();

        if ($hasPendingProfileRequest) {
            return back()->with('status', 'Your profile request is already pending superadmin approval.');
        }

        $closestProfile = ProfileMatchHelper::findClosestMatch(
            $validated['fname'],
            $validated['lname'],
            $validated['mname'] ?? '',
        );

        $action = implode('|', [
            (int) $user->getKey(),
            trim($validated['fname']),
            trim($validated['lname']),
            trim((string) ($validated['mname'] ?? '')),
            (int) ($closestProfile?->getKey() ?? 0),
        ]);

        ActionRequest::query()->create([
            'action' => $action,
            'action_type' => 9,
            'user' => (int) $user->getKey(),
        ]);

        $user->update([
            'profile_pending' => true,
        ]);

        return redirect()->route('dashboard')->with('status', 'Profile request submitted. Waiting for superadmin approval.');
    }
}
