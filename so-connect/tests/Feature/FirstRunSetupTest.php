<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

/**
 * Simulate a "fresh deploy" by temporarily removing the superadmin role from
 * every existing account. Runs inside the test transaction, so it is rolled
 * back afterward and never touches real data permanently. We flip user_type
 * rather than delete to avoid foreign-key constraints on seeded data.
 */
function simulateNoSuperadmin(): void
{
    User::where('user_type', 1)->update(['user_type' => 99]);
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

test('submitting setup creates a superadmin, logs in, and redirects to dashboard', function () {
    simulateNoSuperadmin();

    $email = 'firstrun-'.uniqid().'@example.com';

    $response = $this->post('/setup', [
        'email'                 => $email,
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Sup3r!Secret',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('user_email', $email)->first();
    expect($user)->not->toBeNull();
    expect((int) $user->user_type)->toBe(1);
    expect($user->email_verified_at)->not->toBeNull();
    expect((bool) $user->force_password_change)->toBeFalse();
    expect(Hash::check('Sup3r!Secret', $user->user_password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('setup rejects weak passwords and mismatched confirmation', function () {
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

    expect(User::where('user_type', 1)->count())->toBe(0);
});

test('setup is locked and redirects home once a superadmin exists', function () {
    // Real seeded data already contains superadmins, so do NOT simulate removal.
    expect(User::where('user_type', 1)->exists())->toBeTrue();

    $this->get('/setup')->assertRedirect(route('home'));

    $this->post('/setup', [
        'email'                 => 'late-'.uniqid().'@example.com',
        'password'              => 'Sup3r!Secret',
        'password_confirmation' => 'Sup3r!Secret',
    ])->assertRedirect(route('home'));
});

test('home renders normally when a superadmin exists', function () {
    expect(User::where('user_type', 1)->exists())->toBeTrue();

    $this->get('/')->assertOk();
});
