<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('rejects a wrong current password and leaves the password unchanged', function () {
    $user = recordsUser(3);

    $this->actingAs($user)
        ->from('/settings')
        ->post('/settings/password', [
            'current_password' => 'not-my-password',
            'password' => 'NewStr0ng!Pass',
            'password_confirmation' => 'NewStr0ng!Pass',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->user_password))->toBeTrue();
});

it('rejects a new password that fails the strong-password policy', function () {
    $user = recordsUser(3);

    $this->actingAs($user)
        ->from('/settings')
        ->post('/settings/password', [
            'current_password' => 'password',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()->user_password))->toBeTrue();
});

it('rejects reusing the current password', function () {
    $user = recordsUser(3);
    // Give the account a *strong* current password so the reuse check is the
    // thing that trips, not the strong-password policy.
    $user->user_password = 'Str0ng!Current';
    $user->save();

    $this->actingAs($user)
        ->from('/settings')
        ->post('/settings/password', [
            'current_password' => 'Str0ng!Current',
            'password' => 'Str0ng!Current',
            'password_confirmation' => 'Str0ng!Current',
        ])
        ->assertRedirect('/settings')
        ->assertSessionHasErrors('password');
});

it('changes the password, keeps the force-change flag clear, and audits it', function () {
    // Note: a user with force_password_change=true is redirected to the
    // first-login wizard by EnsurePasswordChanged and never reaches this form,
    // so the reachable state is an already-cleared flag.
    $user = recordsUser(3);

    $this->actingAs($user)
        ->post('/settings/password', [
            'current_password' => 'password',
            'password' => 'NewStr0ng!Pass',
            'password_confirmation' => 'NewStr0ng!Pass',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $fresh = $user->fresh();
    expect(Hash::check('NewStr0ng!Pass', $fresh->user_password))->toBeTrue();
    expect((bool) $fresh->force_password_change)->toBeFalse();

    expect(DB::table('action_logs')
        ->where('category', 'auth')
        ->where('action', 'password_changed')
        ->exists())->toBeTrue();
});
