<?php

namespace App\Http\Controllers;

use App\Helpers\ProfileMatchHelper;
use App\Models\Approval;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Support\SignatureImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function profileForm()
    {
        return view('pages.profile.create');
    }

    public function store(Request $request, \App\Services\RequestTypeService $requestTypeService)
    {
        $validated = $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'mname' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'digits:10', 'starts_with:9'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'sex' => ['nullable', 'in:Male,Female'],
            'religion' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'course_year' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'string'],
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
            'request_type_id' => (int) $requestTypeService->resolveSystemType(
                RequestType::SYSTEM_KEY_PROFILE_MATCH,
                'Profile Match Request',
                RequestType::CATEGORY_ROLE_SECURITY,
                (int) $user->getKey(),
            )->getKey(),
            'organization_id' => null,
            'requested_by' => (int) $user->getKey(),
            'payload' => [
                'user_id' => (int) $user->getKey(),
                'first_name' => $validated['fname'],
                'last_name' => $validated['lname'],
                'middle_name' => $validated['mname'] ?? '',
                'contact_number' => $validated['contact_number'] ?? null,
                'age' => $validated['age'] ?? null,
                'sex' => $validated['sex'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'course_year' => $validated['course_year'] ?? null,
                'signature_path' => SignatureImage::storeDataUrl(
                    $validated['signature'] ?? null,
                    'signatures/profiles',
                ),
                'suggested_profile_id' => (int) ($closestProfile?->getKey() ?? 0),
            ],
            'user' => (int) $user->getKey(),
            'requested_at' => now(),
        ]);

        $user->update([
            'profile_pending' => true,
        ]);

        return redirect()->route('dashboard')->with('status', 'Profile request submitted. Waiting for superadmin approval.');
    }

    /**
     * Save the signed-in user's signature from the profile page: either a
     * drawn data-URL (`signature`) or an uploaded PNG/JPG (`signature_file`).
     */
    public function updateSignature(Request $request)
    {
        $validated = $request->validate([
            'signature' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $profile = $request->user()?->profile()->first();
        if (! $profile) {
            return redirect()->route('profile.create')
                ->with('status', 'Create your profile before adding a signature.');
        }

        $path = null;
        if ($request->hasFile('signature_file') && $request->file('signature_file')->isValid()) {
            $path = $request->file('signature_file')->store('signatures/profiles', SignatureImage::disk());
        } else {
            $path = SignatureImage::storeDataUrl($validated['signature'] ?? null, 'signatures/profiles');
        }

        if ($path === null) {
            return redirect()->route('profile')
                ->with('toast_error', 'Draw or upload a signature first.');
        }

        $old = $profile->signature_path;
        $profile->update(['signature_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk(SignatureImage::disk())->delete($old);
        }

        return redirect()->route('profile')->with('toast', 'Saved!');
    }
}
