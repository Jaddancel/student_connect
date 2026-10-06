<?php

namespace App\Forms\Handlers;

use App\Mail\AccountRequestReceivedMail;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\OrganizationInvitation;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Support\OfficerProfileData;
use App\Support\SignupRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign Up: a bound form's submission requests a new user account — the
 * action_type=11 request an admin reviews on the Promotion Requests page.
 *
 * The applicant's account is created here and now, as a pending "guest"
 * ({@see User::TYPE_GUEST}): unverified, with no organization membership, so it
 * clears none of the officer gates. A confirmation email unlocks it; from then
 * on they can sign in and follow the request on the guest dashboard. Admin
 * approval promotes the same account to officer — it never creates a second one.
 *
 * A rejected applicant re-applies from that dashboard: they are signed in as
 * their own guest account, so a resubmission updates it in place and files a
 * fresh request rather than colliding with its own email address.
 */
class SignUpHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    /** How long the emailed confirmation link stays valid. */
    private const CONFIRMATION_TTL_DAYS = 7;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $values = $this->requirePayloadKeys($form, $payload, ['first_name', 'last_name']);
        $email = $this->resolveOfficerEmail($form, $payload);
        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'form' => 'This form is bound to the "'.\App\Forms\SystemFunction::label((string) $form->system_function)
                    .'" function but is missing a required "'.\App\Forms\FieldType::label(\App\Forms\FieldType::NEW_OFFICER_EMAIL).'" field.',
            ]);
        }

        $applicant = self::pendingApplicant($request);

        $invitation = $this->invitationContext($request, $form, $payload);
        if ($invitation !== null) {
            if ($email !== $invitation['email']) {
                throw ValidationException::withMessages([
                    'form' => 'The email address must match the organization invitation.',
                ]);
            }

            $submittedOrgId = (int) ($this->payloadValue($form, $payload, 'organization_id') ?? 0);
            if ($submittedOrgId !== (int) $invitation['organization_id']) {
                throw ValidationException::withMessages([
                    'form' => 'The organization must match the organization invitation.',
                ]);
            }
        }

        // Re-applying keeps the account: only a DIFFERENT account owning the
        // address is a conflict.
        $taken = User::query()
            ->where('user_email', $email)
            ->when($applicant, fn ($query) => $query->whereKeyNot($applicant->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'form' => 'An account with this email address already exists.',
            ]);
        }

        // One request in the queue at a time: without this, reopening the form
        // while a decision is pending would file a second copy of it.
        if ($applicant && SignupRequests::statusFor($applicant)['status'] === 'pending') {
            throw ValidationException::withMessages([
                'form' => 'Your sign-up is already being reviewed — you can resubmit if it is not approved.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $organizationId = (int) ($this->payloadValue($form, $payload, 'organization_id') ?? 0);
        $email = (string) $this->resolveOfficerEmail($form, $payload);

        $requestPayload = array_merge($payload, [
            'first_name' => (string) $this->payloadValue($form, $payload, 'first_name'),
            'middle_name' => (string) ($this->payloadValue($form, $payload, 'middle_name') ?? ''),
            'last_name' => (string) $this->payloadValue($form, $payload, 'last_name'),
            'email' => $email,
            'organization_id' => $organizationId,
            'signature' => (string) ($this->payloadValue($form, $payload, 'signature') ?? ''),
            'form_submission_id' => (int) $submission->getKey(),
        ]);

        // The Password field type stores its value pre-hashed in the payload
        // (see FormRenderController::submit), which is exactly what the
        // approval flow expects — so it is passed through untouched. A form
        // that maps a plain text field to `password` instead is hashed here as
        // a safety net (bcrypt hashes are left as-is).
        $password = (string) ($this->payloadValue($form, $payload, 'password') ?? '');
        if ($password !== '' && ! str_starts_with($password, '$2')) {
            $password = Hash::make($password);
            $requestPayload['password'] = $password;
        }

        $applicant = self::pendingApplicant($request);
        $isRetry = $applicant !== null;
        $invitation = $this->invitationContext($request, $form, $payload);

        [$applicant, $rawToken] = DB::transaction(function () use ($applicant, $requestPayload, $email, $password, $organizationId, $form, $submission, $invitation) {
            $applicant = $this->storeApplicant($applicant, $requestPayload, $email, $password);

            $submission->update(['submitted_by' => (int) $applicant->getKey()]);

            $requestExtras = [
                'pending_user_id' => (int) $applicant->getKey(),
            ];

            if ($invitation !== null) {
                $requestExtras['invitation_id'] = $invitation['invitation_id'];
                $requestExtras['intended_role'] = $invitation['role'];
                $requestExtras['intended_position'] = $invitation['position'];

                OrganizationInvitation::query()
                    ->whereKey($invitation['invitation_id'])
                    ->whereNull('redeemed_at')
                    ->update(['redeemed_at' => now()]);
            }

            ActionRequest::query()->create([
                'action' => "0|{$organizationId}|new_officer",
                'action_type' => 11,
                'form_id' => (int) $form->getKey(),
                'payload' => array_merge($requestPayload, $requestExtras),
                'user' => (int) $applicant->getKey(),
                'requested_at' => now(),
            ]);

            // A confirmed applicant re-applying stays confirmed; only a brand
            // new account needs a link to prove the address is theirs.
            $token = $applicant->hasVerifiedEmail() ? null : $this->issueConfirmationToken($email);

            return [$applicant, $token];
        });

        if ($rawToken !== null) {
            $this->sendConfirmation($email, $requestPayload, $rawToken);
        }

        $request->session()->put('signup.email', $email);
        $request->session()->put('signup.confirmed', $applicant->hasVerifiedEmail());

        return redirect()->route('signup.success')
            ->with('success', $isRetry
                ? 'Your sign-up was resubmitted — an admin will review it again.'
                : 'Sign-up submitted.');
    }

    /**
     * The replacement sign-up email field is identified by type, so account
     * creation cannot accidentally read a generic email field on the form.
     *
     * @param  array<string,mixed>  $payload
     */
    private function resolveOfficerEmail(Form $form, array $payload): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->field_type !== \App\Forms\FieldType::NEW_OFFICER_EMAIL
                || ! array_key_exists($field->field_key, $payload)) {
                continue;
            }

            $value = $payload[$field->field_key];

            return is_string($value) ? strtolower(trim($value)) : null;
        }

        return null;
    }

    /**
     * Decode and validate an encrypted organization invitation payload carried
     * as a hidden input. Returns the invitation data only when it is unexpired,
     * unredeemed, and matches the submitted organization/email.
     *
     * @return array{invitation_id:int,email:string,organization_id:int,role:string,position:?string}|null
     */
    private function invitationContext(Request $request, Form $form, array $payload): ?array
    {
        $token = (string) ($payload['invitation_prefill'] ?? $request->input('invitation_prefill', ''));
        if ($token === '') {
            return null;
        }

        $rawToken = base64_decode($token, true);
        if ($rawToken === false) {
            return null;
        }

        try {
            $decrypted = json_decode(Crypt::decryptString($rawToken), true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decrypted) || ! isset($decrypted['email'], $decrypted['organization_id'], $decrypted['invitation_id'], $decrypted['token'])) {
            return null;
        }

        $invitation = OrganizationInvitation::query()
            ->whereKey((int) $decrypted['invitation_id'])
            ->where('token_hash', hash('sha256', (string) $decrypted['token']))
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($invitation === null) {
            return null;
        }

        return [
            'invitation_id' => (int) $invitation->getKey(),
            'email' => strtolower((string) $decrypted['email']),
            'organization_id' => (int) $decrypted['organization_id'],
            'role' => (string) ($decrypted['role'] ?? 'officer'),
            'position' => $decrypted['position'] ?? null,
        ];
    }

    /**
     * The guest account behind this submission, when a rejected applicant is
     * re-applying while signed in. Anyone else (including officers and admins
     * filling the form for someone) creates a fresh account.
     */
    private static function pendingApplicant(Request $request): ?User
    {
        $user = $request->user();

        return $user && $user->isGuest() ? $user : null;
    }

    /**
     * Create — or, for a re-application, refresh — the pending account and its
     * profile from the submitted payload.
     *
     * @param  array<string,mixed>  $payload
     */
    private function storeApplicant(?User $applicant, array $payload, string $email, string $password): User
    {
        if ($applicant === null) {
            $address = OfficerProfileData::createAddress($payload);
            $profile = Profile::create(OfficerProfileData::fromPayload($payload, (int) $address->profile_address_id));

            return User::create([
                'user_email' => $email,
                'google_id' => $payload['google_id'] ?? null,
                // A Google-linked email is already verified by Google.
                'email_verified_at' => ! empty($payload['google_id']) ? now() : null,
                'user_password' => $password !== '' ? $password : Hash::make(Str::random(16)),
                'user_type' => User::TYPE_GUEST,
                'profile' => (int) $profile->profile_id,
                'profile_pending' => false,
            ]);
        }

        $profile = $applicant->profile()->first();
        if ($profile) {
            $profile->update(OfficerProfileData::fromPayload($payload));
        } else {
            $address = OfficerProfileData::createAddress($payload);
            $profile = Profile::create(OfficerProfileData::fromPayload($payload, (int) $address->profile_address_id));
            $applicant->forceFill(['profile' => (int) $profile->profile_id])->save();
        }

        $applicant->forceFill(array_filter([
            'user_email' => $email,
            // Blank on a retry means "keep the password you already set".
            'user_password' => $password !== '' ? $password : null,
        ]))->save();

        return $applicant->refresh();
    }

    /** Issue (or replace) this address's confirmation token; returns the raw one. */
    private function issueConfirmationToken(string $email): string
    {
        $rawToken = Str::random(64);

        DB::table('invitation_tokens')->updateOrInsert(
            ['user_email' => $email],
            [
                'token' => hash('sha256', $rawToken),
                'created_at' => now(),
                'expires_at' => now()->addDays(self::CONFIRMATION_TTL_DAYS),
            ],
        );

        return $rawToken;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function sendConfirmation(string $email, array $payload, string $rawToken): void
    {
        try {
            Mail::to($email)->send(new AccountRequestReceivedMail(
                firstName: (string) ($payload['first_name'] ?? ''),
                confirmUrl: route('invitation.verify', ['token' => $rawToken]),
                expiresInDays: self::CONFIRMATION_TTL_DAYS,
            ));
        } catch (\Throwable $e) {
            // A mail outage must not lose the sign-up: the request is already
            // filed, and the success page tells them what the email contains.
            Log::warning('Sign-up confirmation email failed: '.$e->getMessage());
        }
    }
}
