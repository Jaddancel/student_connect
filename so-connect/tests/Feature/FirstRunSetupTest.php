<?php

use App\Mail\SuperadminSetupConfirmationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(DatabaseTransactions::class);

/**
 * Simulate a "fresh deploy" by temporarily removing the superadmin role from
 * every existing account. Runs inside the test transaction, so it is rolled
 * back afterward and never touches real data permanently. We flip user_type
 * rather than delete to avoid foreign-key constraints on seeded data.
 */
function simulateNoSuperadmin(): void
{
    User::where('user_type', User::TYPE_SUPERADMIN)->update(['user_type' => 99]);
}

/**
 * The testing database is not seeded, so the "already initialized" cases make
 * their own *confirmed* superadmin. Rolled back with the test transaction.
 */
function ensureSuperadminExists(): User
{
    return User::create([
        'user_email'        => 'existing-superadmin-'.uniqid().'@example.com',
        'user_password'     => Hash::make('Sup3r!Secret'),
        'user_type'         => User::TYPE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);
}

/** Run the setup form and return the created (still unconfirmed) superadmin. */
function submitSetupForm(string $email): User
{
    test()->post('/setup', [
        'email'                 => $email,
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Sup3r!Secret',
    ]);

    return User::where('user_email', $email)->firstOrFail();
}

/** Pull the confirmation URL out of the mail that setup just queued. */
function capturedConfirmationUrl(): string
{
    $mailable = null;

    Mail::assertSent(SuperadminSetupConfirmationMail::class, function ($mail) use (&$mailable) {
        $mailable = $mail;

        return true;
    });

    return $mailable->confirmationUrl;
}

test('home redirects to setup when no superadmin exists', function () {
    simulateNoSuperadmin();

    $this->get('/')->assertRedirect(route('setup.create'));
    $this->get('/login')->assertRedirect(route('setup.create'));
    $this->get('/dashboard')->assertRedirect(route('setup.create'));
});

test('setup page is reachable when no superadmin exists', function () {
    simulateNoSuperadmin();

    $this->get('/setup')
        ->assertOk()
        ->assertSee('Create the Superadmin Account');
});

test('submitting setup creates an unconfirmed superadmin and mails a confirmation link', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $email = 'firstrun-'.uniqid().'@example.com';

    $this->post('/setup', [
        'email'                 => $email,
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Sup3r!Secret',
    ])->assertRedirect(route('setup.pending'));

    $user = User::where('user_email', $email)->first();
    expect($user)->not->toBeNull();
    expect((int) $user->user_type)->toBe(User::TYPE_SUPERADMIN);
    expect(Hash::check('Sup3r!Secret', $user->user_password))->toBeTrue();

    // The whole point: no access until the address is confirmed.
    expect($user->email_verified_at)->toBeNull();
    $this->assertGuest();

    Mail::assertSent(SuperadminSetupConfirmationMail::class, fn ($mail) => $mail->hasTo($email));
    expect(DB::table('invitation_tokens')->where('user_email', $email)->exists())->toBeTrue();
});

test('an unconfirmed superadmin cannot sign in', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $email = 'unconfirmed-'.uniqid().'@example.com';
    submitSetupForm($email);

    $credentials = ['user_email' => $email, 'user_password' => 'Sup3r!Secret'];

    // While the system counts as uninitialized, login is not even reachable —
    // every route funnels back into setup.
    $this->post('/login', $credentials)->assertRedirect(route('setup.create'));
    $this->assertGuest();

    // And once login *is* reachable, the credentials are still refused: right
    // password, unconfirmed address.
    ensureSuperadminExists();

    $this->post('/login', $credentials)->assertSessionHasErrors('user_email');
    $this->assertGuest();
});

test('an unconfirmed superadmin leaves the system uninitialized', function () {
    Mail::fake();
    simulateNoSuperadmin();

    submitSetupForm('halfway-'.uniqid().'@example.com');

    // Still funnelled into setup, and setup shows the holding page rather than
    // a second signup form — a mistyped address must not brick the deployment.
    $this->get('/')->assertRedirect(route('setup.create'));
    $this->get('/setup')->assertRedirect(route('setup.pending'));
    $this->get('/setup/confirm')->assertOk()->assertSee('Check your inbox');
});

test('opening the confirmation link activates the account and signs it in', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $email = 'confirm-'.uniqid().'@example.com';
    $user  = submitSetupForm($email);

    $this->get(capturedConfirmationUrl())->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user->fresh());

    // The one-time token is consumed.
    expect(DB::table('invitation_tokens')->where('user_email', $email)->exists())->toBeFalse();
});

test('an invalid or expired confirmation link is rejected', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $email = 'expired-'.uniqid().'@example.com';
    $user  = submitSetupForm($email);
    $url   = capturedConfirmationUrl();

    $this->get(route('setup.confirm', ['token' => 'not-a-real-token']))
        ->assertRedirect(route('setup.pending'));

    DB::table('invitation_tokens')->where('user_email', $email)
        ->update(['expires_at' => now()->subMinute()]);

    $this->get($url)->assertRedirect(route('setup.pending'));

    expect($user->fresh()->email_verified_at)->toBeNull();
    $this->assertGuest();
});

test('resending issues a new link and invalidates the old one', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $email   = 'resend-'.uniqid().'@example.com';
    submitSetupForm($email);
    $firstUrl = capturedConfirmationUrl();

    $this->post('/setup/confirm/resend')->assertRedirect(route('setup.pending'));

    Mail::assertSentCount(2);

    // One token row per address, so the superseded link no longer resolves.
    $this->get($firstUrl)->assertRedirect(route('setup.pending'));
    $this->assertGuest();
});

test('setup can be restarted to fix a mistyped address', function () {
    Mail::fake();
    simulateNoSuperadmin();

    $typo = 'typo-'.uniqid().'@example.com';
    submitSetupForm($typo);

    $this->post('/setup/restart')->assertRedirect(route('setup.create'));

    expect(User::where('user_email', $typo)->exists())->toBeFalse();
    expect(DB::table('invitation_tokens')->where('user_email', $typo)->exists())->toBeFalse();

    // The form is available again for the correct address.
    $this->get('/setup')->assertOk()->assertSee('Create the Superadmin Account');
});

test('setup rejects weak passwords and mismatched confirmation', function () {
    Mail::fake();
    simulateNoSuperadmin();

    // Missing uppercase/number/special and too short.
    $this->post('/setup', [
        'email'                 => 'weak-'.uniqid().'@example.com',
        'password'              => 'lowercase',
        'password_confirmation' => 'lowercase',
    ])->assertSessionHasErrors('password');

    // Mismatched confirmation.
    $this->post('/setup', [
        'email'                 => 'mismatch-'.uniqid().'@example.com',
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Different!1',
    ])->assertSessionHasErrors('password');

    expect(User::where('user_type', User::TYPE_SUPERADMIN)->count())->toBe(0);
    Mail::assertNothingSent();
});

test('setup is locked and redirects home once a superadmin is confirmed', function () {
    ensureSuperadminExists();

    $this->get('/setup')->assertRedirect(route('home'));
    $this->get('/setup/confirm')->assertRedirect(route('home'));

    $this->post('/setup', [
        'email'                 => 'late-'.uniqid().'@example.com',
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Sup3r!Secret',
    ])->assertRedirect(route('home'));
});

test('home renders normally when a superadmin exists', function () {
    ensureSuperadminExists();

    $this->get('/')->assertOk();
});
