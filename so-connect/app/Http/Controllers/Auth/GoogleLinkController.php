<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    /**
     * Sensitive People API scopes needed on top of the basic profile/email to
     * read the applicant's gender, birthday and address. Google flags these as
     * "sensitive" — the OAuth consent screen shows them explicitly, and Google
     * only returns a value for whichever of these the user actually has set
     * (and consents to share) on their Google Account.
     */
    private const PEOPLE_SCOPES = [
        'https://www.googleapis.com/auth/user.gender.read',
        'https://www.googleapis.com/auth/user.birthday.read',
        'https://www.googleapis.com/auth/user.addresses.read',
    ];

    public function redirect(Request $request)
    {
        if (! self::isConfigured()) {
            return $this->closePopup(['error' => 'Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.']);
        }

        return Socialite::driver('google')
            ->stateless()
            ->scopes(array_merge(['openid', 'profile', 'email'], self::PEOPLE_SCOPES))
            ->redirect();
    }

    public function callback(Request $request)
    {
        if (! self::isConfigured()) {
            return $this->closePopup(['error' => 'Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.']);
        }

        try {
            $google = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return $this->closePopup(['error' => 'Google sign-in failed or was cancelled. Please try again.']);
        }

        return $this->closePopup(array_merge([
            'google_id' => (string) $google->getId(),
            'email' => (string) $google->getEmail(),
            'first_name' => (string) ($google->user['given_name'] ?? ''),
            'last_name' => (string) ($google->user['family_name'] ?? ''),
            'name' => (string) $google->getName(),
        ], $this->fetchPersonDetails((string) $google->token, (array) ($google->approvedScopes ?? []))));
    }

    /**
     * Best-effort fetch of gender/birthday/address from the People API using
     * the OAuth access token just granted. Any failure (missing consent,
     * network error, no data set on the account) is swallowed — these fields
     * are a bonus pre-fill, never a reason to fail the sign-in.
     *
     * @param  array<int,string>  $approvedScopes  Scopes Google actually granted
     * @return array{sex?:string,birthday?:string,present_address?:string}
     */
    private function fetchPersonDetails(string $accessToken, array $approvedScopes = []): array
    {
        if ($accessToken === '') {
            return [];
        }

        $missingScopes = array_diff(self::PEOPLE_SCOPES, $approvedScopes);
        if ($missingScopes !== []) {
            Log::info('Google sign-in: People API scopes not granted — extra prefill fields will be empty.', [
                'missing_scopes' => array_values($missingScopes),
                'granted_scopes' => $approvedScopes,
            ]);
        }

        try {
            $response = Http::withToken($accessToken)
                ->get('https://people.googleapis.com/v1/people/me', [
                    'personFields' => 'genders,birthdays,addresses',
                ]);
        } catch (\Throwable $e) {
            Log::info('Google People API request failed.', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            // Most common causes: People API not enabled in the Google Cloud
            // project, or the sensitive scopes not added to the OAuth consent
            // screen. Log it instead of silently dropping the prefill.
            Log::info('Google People API returned an error — extra prefill fields will be empty.', [
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return [];
        }

        $result = [];

        $genderValue = strtolower((string) ($response->json('genders.0.value') ?? ''));
        if ($genderValue === 'male' || $genderValue === 'female') {
            $result['sex'] = ucfirst($genderValue);
        }

        $date = $response->json('birthdays.0.date');
        if (is_array($date) && ! empty($date['year']) && ! empty($date['month']) && ! empty($date['day'])) {
            $result['birthday'] = sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']);
        }

        $address = $response->json('addresses.0.formattedValue');
        if (is_string($address) && $address !== '') {
            $result['present_address'] = $address;
        }

        if ($result === []) {
            Log::info('Google People API returned no gender/birthday/address — the account likely has none of these set.');
        }

        return $result;
    }

    /**
     * Whether the three GOOGLE_* env keys are filled. Exposed publicly so
     * views can skip opening the popup altogether (and show the message
     * immediately) instead of flashing an empty window that closes itself.
     */
    public static function isConfigured(): bool
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
