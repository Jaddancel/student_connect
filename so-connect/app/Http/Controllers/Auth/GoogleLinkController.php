<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Sign in with Google" for the admin/officer account-creation forms. Runs the
 * Google OAuth flow in a popup and hands the applicant's Google profile back to
 * the opener window to pre-fill the form and tie the account.
 *
 * Crucially STATELESS and login-free: it never calls Auth::login, so the
 * signed-in SuperAdmin/Admin's session is untouched — the applicant simply
 * authorizes with Google while physically present (or via an authorized rep).
 */
class GoogleLinkController extends Controller
{
    public function redirect(Request $request)
    {
        if (! $this->configured()) {
            return $this->closePopup(['error' => 'Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.']);
        }

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if (! $this->configured()) {
            return $this->closePopup(['error' => 'Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.']);
        }

        try {
            $google = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return $this->closePopup(['error' => 'Google sign-in failed or was cancelled. Please try again.']);
        }

        return $this->closePopup([
            'google_id' => (string) $google->getId(),
            'email' => (string) $google->getEmail(),
            'first_name' => (string) ($google->user['given_name'] ?? ''),
            'last_name' => (string) ($google->user['family_name'] ?? ''),
            'name' => (string) $google->getName(),
        ]);
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * Render a tiny page that posts the payload back to the opener and closes.
     *
     * @param  array<string,string>  $payload
     */
    private function closePopup(array $payload)
    {
        return response()->view('pages.auth.google-callback', ['payload' => $payload]);
    }
}
