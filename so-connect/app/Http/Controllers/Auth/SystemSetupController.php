<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SuperadminSetupConfirmationMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * First-run setup: creates the one superadmin account a fresh deployment needs.
 *
 * The account is created *unverified* and is not signed in. It only becomes
 * usable once the confirmation link mailed to the address is opened, which
 * proves whoever ran the installer actually controls that mailbox. Until then
 * the system counts as uninitialized, so {@see \App\Http\Middleware\EnsureSystemInitialized}
 * keeps funnelling traffic back here rather than letting a typo'd address lock
 * everyone out permanently.
 */
class SystemSetupController extends Controller
{
    /** How long a confirmation link stays valid. */
    private const TOKEN_TTL_HOURS = 72;

    public function create()
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        // A superadmin awaiting confirmation already exists: don't offer a
        // second signup form, show them where their confirmation stands.
        if ($this->pendingSuperadmin()) {
            return redirect()->route('setup.pending');
        }

        return view('pages.auth.superadmin-setup', [
            'title' => 'System Setup',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        // Guard against races / double-submit creating a second superadmin.
        if ($this->pendingSuperadmin()) {
            return redirect()->route('setup.pending');
        }

        $validated = $request->validate([
            'email'                 => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $password = $validated['password'];

        $failures = [];
        if (! preg_match('/[A-Z]/', $password)) {
            $failures[] = 'at least one uppercase letter';
        }
        if (! preg_match('/[a-z]/', $password)) {
            $failures[] = 'at least one lowercase letter';
        }
        if (! preg_match('/[0-9]/', $password)) {
            $failures[] = 'at least one number';
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $failures[] = 'at least one special character';
        }

        if (! empty($failures)) {
            throw ValidationException::withMessages([
                'password' => 'Password must contain '.implode(', ', $failures).'.',
            ]);
        }

        $user = User::create([
            'user_email'            => $validated['email'],
            'user_password'         => Hash::make($password),
            'user_type'             => User::TYPE_SUPERADMIN,
            'profile'               => null,
            'profile_pending'       => false,
            'force_password_change' => false,
            // Deliberately unverified: the confirmation link sets this.
            'email_verified_at'     => null,
        ]);

        $sent = $this->sendConfirmation($user);

        return redirect()->route('setup.pending')
            ->with($sent ? 'status' : 'mail_error', $sent
                ? 'Confirmation email sent to '.$user->user_email.'.'
                : 'The account was created, but the confirmation email could not be sent. Check the mail configuration, then try again.');
    }

    /**
     * "Check your inbox" holding page shown between account creation and
     * confirmation. This is the only page a half-initialized system serves.
     */
    public function pending()
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        $user = $this->pendingSuperadmin();

        if (! $user) {
            return redirect()->route('setup.create');
        }

        $token = DB::table('invitation_tokens')->where('user_email', $user->user_email)->first();

        return view('pages.auth.superadmin-setup-pending', [
            'title'      => 'Confirm Your Email',
            'email'      => $user->user_email,
            'expiresAt'  => $token?->expires_at,
            'linkExpired' => $token === null || ($token->expires_at !== null && now()->gt($token->expires_at)),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        $user = $this->pendingSuperadmin();

        if (! $user) {
            return redirect()->route('setup.create');
        }

        $sent = $this->sendConfirmation($user);

        return redirect()->route('setup.pending')
            ->with($sent ? 'status' : 'mail_error', $sent
                ? 'A new confirmation email is on its way to '.$user->user_email.'.'
                : 'The confirmation email could not be sent. Check the mail configuration, then try again.');
    }

    /**
     * Open the mailed link: verifies the address, signs the superadmin in, and
     * hands the finished system over.
     */
    public function confirm(Request $request, string $token): RedirectResponse
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        $record = DB::table('invitation_tokens')
            ->where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        $user = $record
            ? User::where('user_email', $record->user_email)
                ->where('user_type', User::TYPE_SUPERADMIN)
                ->whereNull('email_verified_at')
                ->first()
            : null;

        if (! $user) {
            return redirect()->route('setup.pending')
                ->with('mail_error', 'That confirmation link is invalid or has expired. Send yourself a new one.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        DB::table('invitation_tokens')->where('user_email', $user->user_email)->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Email confirmed. Welcome to StudentConnect!');
    }

    /**
     * Discard an unconfirmed superadmin so setup can be run again — the escape
     * hatch for a mistyped address, which would otherwise be unrecoverable
     * without database access.
     */
    public function restart(): RedirectResponse
    {
        if ($redirect = $this->guardAlreadyInitialized()) {
            return $redirect;
        }

        $user = $this->pendingSuperadmin();

        if ($user) {
            DB::table('invitation_tokens')->where('user_email', $user->user_email)->delete();
            $user->delete();
        }

        return redirect()->route('setup.create')
            ->with('status', 'Setup restarted. Enter the correct email address.');
    }

    /**
     * Setup is one-time only: once a confirmed superadmin exists these routes
     * are closed for good.
     */
    private function guardAlreadyInitialized(): ?RedirectResponse
    {
        return User::where('user_type', User::TYPE_SUPERADMIN)->whereNotNull('email_verified_at')->exists()
            ? redirect()->route('home')
            : null;
    }

    /** The superadmin created by setup but not yet confirmed, if any. */
    private function pendingSuperadmin(): ?User
    {
        return User::where('user_type', User::TYPE_SUPERADMIN)
            ->whereNull('email_verified_at')
            ->orderBy('user_id')
            ->first();
    }

    /** Issue a fresh confirmation token and mail it. Returns false if sending failed. */
    private function sendConfirmation(User $user): bool
    {
        $rawToken = Str::random(64);

        DB::table('invitation_tokens')->updateOrInsert(
            ['user_email' => $user->user_email],
            [
                'token'      => hash('sha256', $rawToken),
                'created_at' => now(),
                'expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
            ]
        );

        try {
            Mail::to($user->user_email)->send(new SuperadminSetupConfirmationMail(
                recipientEmail:  $user->user_email,
                confirmationUrl: route('setup.confirm', ['token' => $rawToken]),
                expiresInHours:  self::TOKEN_TTL_HOURS,
            ));
        } catch (\Throwable $e) {
            // Unlike the other invitation mails, a silent failure here would
            // strand the installer on a page they can never leave, so the
            // caller surfaces it instead of swallowing it.
            report($e);

            return false;
        }

        return true;
    }
}
