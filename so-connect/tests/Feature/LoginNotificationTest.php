<?php

use App\Mail\LoginNotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues a login notification for opted-in users on login', function () {
    Mail::fake();

    $user = recordsUser(3);
    expect((bool) $user->notify_on_login)->toBeTrue();

    // Auth::login() fires the Illuminate\Auth\Events\Login event that the
    // SendLoginNotification listener (registered in AppServiceProvider) handles.
    Auth::login($user);

    Mail::assertQueued(
        LoginNotificationMail::class,
        fn (LoginNotificationMail $mail) => $mail->hasTo($user->user_email)
    );
});

it('does not notify users who opted out', function () {
    Mail::fake();

    $user = recordsUser(3);
    $user->forceFill(['notify_on_login' => false])->save();

    Auth::login($user->fresh());

    Mail::assertNotQueued(LoginNotificationMail::class);
});
