<?php

namespace App\Listeners;

use App\Mail\LoginNotificationMail;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a "new sign-in" security notice to users who opted in
 * (users.notify_on_login). Runs synchronously on the login request so the
 * request metadata (IP, user agent) is still available; the actual delivery is
 * queued via the ShouldQueue mailable so login latency is unaffected.
 */
class SendLoginNotification
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user || ! (bool) ($user->notify_on_login ?? false)) {
            return;
        }

        $email = trim((string) ($user->user_email ?? ''));

        if ($email === '') {
            return;
        }

        $request = request();

        Mail::to($email)->queue(new LoginNotificationMail(
            recipientEmail: $email,
            ipAddress: $request?->ip() ?: 'Unknown',
            userAgent: \Illuminate\Support\Str::limit((string) $request?->userAgent(), 255, '') ?: 'Unknown device',
            loggedInAt: now()->format('M j, Y g:i A T'),
        ));
    }
}
