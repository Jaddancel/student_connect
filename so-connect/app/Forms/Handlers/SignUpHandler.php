<?php

namespace App\Forms\Handlers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sign Up: a bound form's submission requests a new user account (user type 3)
 * — the same action_type=11 request the student-leader-directory signup
 * creates, so the existing admin approval (account + profile creation,
 * activation email) applies unchanged.
 */
class SignUpHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $values = $this->requirePayloadKeys($form, $payload, ['email', 'first_name', 'last_name']);

        if (User::query()->where('user_email', (string) $values['email'])->exists()) {
            throw ValidationException::withMessages([
                'form' => 'An account with this email address already exists.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $organizationId = (int) ($this->payloadValue($form, $payload, 'organization_id') ?? 0);

        $requestPayload = array_merge($payload, [
            'first_name' => (string) $this->payloadValue($form, $payload, 'first_name'),
            'middle_name' => (string) ($this->payloadValue($form, $payload, 'middle_name') ?? ''),
            'last_name' => (string) $this->payloadValue($form, $payload, 'last_name'),
            'email' => (string) $this->payloadValue($form, $payload, 'email'),
            'organization_id' => $organizationId,
            'signature' => (string) ($this->payloadValue($form, $payload, 'signature') ?? ''),
            'form_submission_id' => (int) $submission->getKey(),
        ]);

        // A plain-text password field would otherwise land in the payload as
        // typed; the approval flow expects it pre-hashed (or absent — then a
        // random password + activation email is used).
        if (! empty($requestPayload['password'])) {
            $requestPayload['password'] = Hash::make((string) $requestPayload['password']);
        }

        ActionRequest::query()->create([
            'action' => "0|{$organizationId}|new_officer",
            'action_type' => 11,
            'form_id' => (int) $form->getKey(),
            'payload' => $requestPayload,
            'user' => $request->user() ? (int) $request->user()->getKey() : null,
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Sign-up submitted. An admin will review it before the account is created.');
    }
}
