<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminInvitationMail;
use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminAccountCreationController extends Controller
{
    public function create()
    {
        return view('pages.admin.accounts.create', [
            'title' => 'Create Admin Account',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email'                 => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'first_name'            => ['required', 'string', 'max:100'],
            'middle_name'           => ['nullable', 'string', 'max:100'],
            'last_name'             => ['required', 'string', 'max:100'],
            'contact_number'        => ['required', 'string', 'max:50'],
            'age'                   => ['nullable', 'integer', 'min:1', 'max:99'],
            'sex'                   => ['nullable', 'string', 'in:Male,Female'],
            'religious_affiliation' => ['nullable', 'string', 'max:255'],
            'nationality'           => ['nullable', 'string', 'max:255'],
            'birthplace'            => ['nullable', 'string', 'max:255'],
            'birthday'              => ['nullable', 'date'],
            'course'                => ['nullable', 'string', 'max:255'],
            'year_level'            => ['nullable', 'string', 'max:50'],
            'present_address'       => ['nullable', 'string', 'max:500'],
            'home_address'          => ['nullable', 'string', 'max:500'],
            'parents_guardian'      => ['nullable', 'string', 'max:255'],
            'faculty_advisers'      => ['nullable', 'array'],
            'faculty_advisers.*'    => ['nullable', 'string', 'max:255'],
            'photo'                 => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $file      = $request->file('photo');
            $photoPath = $file->storeAs(
                'form-submissions/officer-photos/'.now()->format('Y/m'),
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        DB::transaction(function () use ($validated, $photoPath) {
            $addr = profileAddress::create([
                'country'  => 'Philippines',
                'province' => '',
                'town'     => '',
                'barangay' => $validated['present_address'] ?? '',
            ]);

            $profile = Profile::create([
                'first_name'     => $validated['first_name'],
                'middle_name'    => $validated['middle_name'] ?? '',
                'last_name'      => $validated['last_name'],
                'contact_number' => $validated['contact_number'],
                'age'            => $validated['age'] ?? null,
                'sex'            => $validated['sex'] ?? '',
                'religion'       => $validated['religious_affiliation'] ?? '',
                'nationality'    => $validated['nationality'] ?? '',
                'birthday'       => $validated['birthday'] ?? null,
                'birthplace'     => $validated['birthplace'] ?? '',
                'course_year'    => trim(($validated['course'] ?? '').' '.($validated['year_level'] ?? '')),
                'occupation'     => 'Administrator',
                'address'        => (int) $addr->profile_address_id,
                'photo'          => $photoPath ?? '',
            ]);

            User::create([
                'user_email'            => $validated['email'],
                'user_password'         => Hash::make('tAU100!!'),
                'user_type'             => 2,
                'profile'               => (int) $profile->profile_id,
                'profile_pending'       => false,
                'force_password_change' => true,
            ]);
        });

        $rawToken = Str::random(64);
        DB::table('invitation_tokens')->updateOrInsert(
            ['user_email' => $validated['email']],
            [
                'token'      => hash('sha256', $rawToken),
                'created_at' => now(),
                'expires_at' => now()->addHours(72),
            ]
        );

        $recipientName = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));

        try {
            Mail::to($validated['email'])->send(new AdminInvitationMail(
                recipientEmail: $validated['email'],
                recipientName:  $recipientName,
                activationUrl:  route('invitation.verify', ['token' => $rawToken]),
            ));
        } catch (\Throwable) {
            // Email failure is non-fatal
        }

        return redirect()->route('superadmin.accounts.create')
            ->with('success', 'Admin account created. An invitation email has been sent to '.$validated['email'].'.');
    }
}
