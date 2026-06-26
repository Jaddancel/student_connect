<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OfficerInvitationMail;
use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminOfficerCreationController extends Controller
{
    public function create()
    {
        $organizations = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
            ->orderBy('od.name')
            ->get();

        return view('pages.admin.officers.create', [
            'title'         => 'Create Officer Account',
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id'      => ['required', 'integer', 'exists:organizations,organization_id'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'first_name'           => ['required', 'string', 'max:100'],
            'middle_name'          => ['nullable', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'position'             => ['required', 'string', 'max:255'],
            'contact_number'       => ['required', 'string', 'max:50'],
            'age'                  => ['required', 'integer', 'min:1', 'max:99'],
            'sex'                  => ['required', 'string', 'in:Male,Female'],
            'religious_affiliation'=> ['nullable', 'string', 'max:255'],
            'nationality'          => ['required', 'string', 'max:255'],
            'birthplace'           => ['nullable', 'string', 'max:255'],
            'birthday'             => ['required', 'date'],
            'course'               => ['required', 'string', 'max:255'],
            'year_level'           => ['required', 'string', 'max:50'],
            'country'              => ['required', 'string', 'max:255'],
            'province'             => ['required', 'string', 'max:255'],
            'town'                 => ['required', 'string', 'max:255'],
            'barangay'             => ['required', 'string', 'max:255'],
            'photo'                => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $orgId = (int) $validated['organization_id'];

        $photoPath = '';
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $file      = $request->file('photo');
            $photoPath = $file->storeAs(
                'form-submissions/officer-photos/'.now()->format('Y/m'),
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        DB::transaction(function () use ($validated, $orgId, $photoPath) {
            $addr = profileAddress::create([
                'country'  => $validated['country'],
                'province' => $validated['province'],
                'town'     => $validated['town'],
                'barangay' => $validated['barangay'],
            ]);

            $profile = Profile::create([
                'first_name'    => $validated['first_name'],
                'middle_name'   => $validated['middle_name'] ?? '',
                'last_name'     => $validated['last_name'],
                'contact_number'=> $validated['contact_number'],
                'age'           => $validated['age'],
                'sex'           => $validated['sex'],
                'religion'      => $validated['religious_affiliation'] ?? '',
                'nationality'   => $validated['nationality'],
                'birthday'      => $validated['birthday'],
                'birthplace'    => $validated['birthplace'] ?? '',
                'course_year'   => trim($validated['course'].' - '.$validated['year_level']),
                'occupation'    => 'Student',
                'address'       => (int) $addr->profile_address_id,
                'position'      => $validated['position'],
                'photo'         => $photoPath,
            ]);

            $user = User::create([
                'user_email'     => $validated['email'],
                'user_password'  => 'tAU100!!',
                'user_type'      => 3,
                'profile'        => (int) $profile->profile_id,
                'profile_pending'=> false,
            ]);

            DB::table('organization_officers')->insert([
                'user'          => (int) $user->user_id,
                'approval'      => null,
                'organization'  => $orgId,
                'role'          => 'officer',
                'yearterm'      => null,
                'member_since'  => now(),
                'registered_at' => now(),
                'reassigned_at' => now(),
            ]);
        });

        // Resolve organization name for the invitation email
        $orgName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $orgId)
            ->value(DB::raw("COALESCE(od.name, '')"));

        $rawToken = Str::random(64);
        DB::table('invitation_tokens')->updateOrInsert(
            ['user_email' => $validated['email']],
            [
                'token'      => hash('sha256', $rawToken),
                'created_at' => now(),
                'expires_at' => now()->addHours(72),
            ]
        );

        try {
            Mail::to($validated['email'])->send(new OfficerInvitationMail(
                recipientEmail:   $validated['email'],
                organizationName: (string) ($orgName ?? ''),
                position:         $validated['position'],
                activationUrl:    route('invitation.verify', ['token' => $rawToken]),
            ));
        } catch (\Throwable $e) {
            // Email failure is non-fatal, but must be logged so it is not silently lost.
            Log::error('Officer invitation email failed to send', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.officers.create')
            ->with('success', 'Officer account created. An invitation email has been sent to '.$validated['email'].'.');
    }
}
