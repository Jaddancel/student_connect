<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    public function verify(Request $request): RedirectResponse
    {
        $rawToken = (string) $request->query('token', '');

        if ($rawToken === '') {
            return redirect()->route('login')
                ->withErrors(['user_email' => 'This activation link is invalid or has expired.']);
        }

        $record = DB::table('invitation_tokens')
            ->where('token', hash('sha256', $rawToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('login')
                ->withErrors(['user_email' => 'This activation link is invalid or has expired.']);
        }

        $user = User::where('user_email', $record->user_email)->firstOrFail();

        $user->forceFill(['email_verified_at' => now()])->save();

        DB::table('invitation_tokens')->where('user_email', $record->user_email)->delete();

        // Admin accounts: auto-login and redirect to first-login password wizard
        if ((int) $user->user_type === User::TYPE_ADMIN) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('password.change');
        }

        // Sign-up applicants confirming their address: the account already has
        // the password they chose, so sign them in and show them where their
        // request stands rather than bouncing them to a login form.
        if ($user->isGuest()) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('guest.dashboard')
                ->with('success', 'Email confirmed. An administrator is reviewing your sign-up request.');
        }

        return redirect()->route('login')
            ->with('status', 'Your account has been activated! You can now sign in.');
    }
}
