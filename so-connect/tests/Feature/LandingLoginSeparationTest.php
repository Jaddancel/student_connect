<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::query()->create([
        'user_email' => 'system-admin@example.com',
        'user_password' => 'password',
        'user_type' => User::TYPE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);
});

it('keeps the public landing page focused and links to a separate login page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('login'), false)
        ->assertSee('Discover. Participate. Lead.')
        ->assertDontSee('id="auth-form"', false)
        ->assertDontSee('Welcome back');
});

it('renders login as its own branded page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Sign in securely')
        ->assertSee('Register with Google')
        ->assertSee('Back to public site')
        ->assertSee('SO Connect')
        ->assertDontSee('TailAdmin');
});

it('returns failed authentication to the dedicated login page with the email preserved', function () {
    $this->from(route('login'))->post('/login', [
        'user_email' => 'missing@example.com',
        'user_password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('user_email')
        ->assertSessionHasInput('user_email', 'missing@example.com');
});

it('redirects the legacy signin URL to the dedicated login page', function () {
    $this->get(route('signin'))->assertRedirect(route('login'));
});
