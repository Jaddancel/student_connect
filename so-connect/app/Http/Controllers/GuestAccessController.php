<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Models\Organization;
use App\Models\User;
use App\Support\SignupRequests;
use Illuminate\Http\Request;

/**
 * The two pages a sign-up applicant sees before an admin decides on them: the
 * post-submit success page (public) and the guest dashboard (their own account).
 *
 * A guest holds no organization membership, so every officer gate already turns
 * them away; this is the one place built for them.
 */
class GuestAccessController extends Controller
{
    /** Post-submit page: what was sent, what to do next. */
    public function success(Request $request)
    {
        $email = (string) $request->session()->get('signup.email', '');

        // Nothing to report (deep link, or the session has since been replaced)
        // — send them somewhere useful instead of an empty page.
        if ($email === '') {
            return $request->user()?->isGuest()
                ? redirect()->route('guest.dashboard')
                : redirect()->route('login');
        }

        return view('pages.auth.signup-success', [
            'title' => 'Sign-up submitted',
            'email' => $email,
            'confirmed' => (bool) $request->session()->get('signup.confirmed', false),
        ]);
    }

    /** Guest dashboard: the status of their sign-up request and what happens next. */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Approved applicants are officers by then; everyone else has a real
        // dashboard of their own.
        if (! $user->isGuest()) {
            return redirect()->route('dashboard');
        }

        $state = SignupRequests::statusFor($user);
        $payload = (array) ($state['request']?->payload ?? []);
        $organizationId = (int) ($payload['organization_id'] ?? 0);

        return view('pages.guest.dashboard', [
            'title' => 'Account request',
            'status' => $state['status'],
            'reason' => $state['reason'],
            'submittedAt' => $state['request']?->requested_at,
            'decidedAt' => $state['approval']?->approved_at,
            'attemptsLeft' => max(0, RequestDecisionController::MAX_SIGNUP_REJECTIONS - $state['rejections']),
            'organizationName' => $this->organizationName($organizationId),
            'details' => array_filter([
                'Name' => trim(($payload['first_name'] ?? '').' '.($payload['last_name'] ?? '')),
                'Email' => (string) $user->user_email,
                'Student ID' => (string) ($payload['student_id'] ?? ''),
                'Position' => (string) ($payload['position'] ?? ''),
                'Contact number' => (string) ($payload['contact_number'] ?? ''),
            ], fn ($value) => $value !== ''),
            'retryUrl' => $this->signUpFormUrl(),
        ]);
    }

    /** The sign-up form's URL, when a form is bound to the sign-up function. */
    private function signUpFormUrl(): ?string
    {
        $form = SystemFunction::form(SystemFunction::SIGN_UP);

        return $form && $form->route_name ? route('forms.render', $form->route_name) : null;
    }

    private function organizationName(int $organizationId): string
    {
        if ($organizationId <= 0) {
            return '';
        }

        // `detail` is a FK column on organizations and shadows the relation, so
        // `$organization->detail` returns the id — load the related row.
        $organization = Organization::query()->find($organizationId);

        return (string) ($organization?->detail()->first()?->name ?? '');
    }

    /** Guests belong on their own page — used by the shared dashboard route. */
    public static function isGuest(?User $user): bool
    {
        return $user !== null && $user->isGuest();
    }
}
