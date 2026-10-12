<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminInvitationMail;
use App\Models\User;
use App\Services\AccountCreationService;
use App\Support\SignatureImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminAccountCreationController extends Controller
{
    public function create()
    {
        return $this->createForm(User::TYPE_ADMIN);
    }

    public function createSuperadmin()
    {
        return $this->createForm(User::TYPE_SUPERADMIN);
    }

    private function createForm(int $userType)
    {
        $accountLabel = $userType === User::TYPE_SUPERADMIN ? 'Superadmin' : 'Admin';

        return view('pages.admin.accounts.create', [
            'title' => "Create {$accountLabel} Account",
            'accountLabel' => $accountLabel,
            'storeRoute' => $userType === User::TYPE_SUPERADMIN
                ? 'superadmin.superadmins.store'
                : 'superadmin.accounts.store',
        ]);
    }

    public function store(Request $request)
    {
        return $this->storeAccount($request, User::TYPE_ADMIN);
    }

    public function storeSuperadmin(Request $request)
    {
        return $this->storeAccount($request, User::TYPE_SUPERADMIN);
    }

    private function storeAccount(Request $request, int $userType)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:50'],
            'age' => ['nullable', 'integer', 'min:1', 'max:99'],
            'sex' => ['nullable', 'string', 'in:Male,Female'],
            'religious_affiliation' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'course' => ['nullable', 'string', 'max:255'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'parents_guardian' => ['nullable', 'string', 'max:255'],
            'faculty_advisers' => ['nullable', 'array'],
            'faculty_advisers.*' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
            'signature' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,heic', 'max:5120'],
            // Optional Google link captured by the "Sign in with Google" popup.
            'google_id' => ['nullable', 'string', 'max:255', 'unique:users,google_id'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $file = $request->file('photo');
            $photoPath = $file->storeAs(
                'form-submissions/officer-photos/'.now()->format('Y/m'),
                Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $uploadedSig = $request->hasFile('signature_file') && $request->file('signature_file')->isValid();
        $sigPath = $uploadedSig
            ? SignatureImage::store(file_get_contents($request->file('signature_file')->getRealPath()), 'signatures/profiles')
            : SignatureImage::storeDataUrl($validated['signature'] ?? null, 'signatures/profiles');

        app(AccountCreationService::class)->create($validated, $userType, $photoPath, $sigPath);

        $rawToken = Str::random(64);
        DB::table('invitation_tokens')->updateOrInsert(
            ['user_email' => $validated['email']],
            [
                'token' => hash('sha256', $rawToken),
                'created_at' => now(),
                'expires_at' => now()->addHours(72),
            ]
        );

        $accountLabel = $userType === User::TYPE_SUPERADMIN ? 'Superadmin' : 'Admin';
        $recipientName = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));

        $mailSent = true;
        try {
            Mail::to($validated['email'])->send(new AdminInvitationMail(
                recipientEmail: $validated['email'],
                recipientName: $recipientName,
                activationUrl: route('invitation.verify', ['token' => $rawToken]),
                accountRole: $accountLabel,
            ));
        } catch (\Throwable $exception) {
            report($exception);
            $mailSent = false;
        }

        $createRoute = $userType === User::TYPE_SUPERADMIN
            ? 'superadmin.superadmins.create'
            : 'superadmin.accounts.create';

        return redirect()->route($createRoute)
            ->with(
                $mailSent ? 'success' : 'mail_error',
                $mailSent
                    ? "{$accountLabel} account created. An invitation email has been sent to {$validated['email']}."
                    : "{$accountLabel} account created, but the invitation email could not be sent. Check the mail configuration.",
            );
    }
}
