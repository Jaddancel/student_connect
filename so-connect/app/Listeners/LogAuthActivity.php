<?php

namespace App\Listeners;

use App\Models\LoginLog;
use App\Services\ActionLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handle(Login|Logout $event): void
    {
        LoginLog::create([
            'user_id'     => $event->user?->getAuthIdentifier(),
            'interaction' => $event instanceof Login ? 'LOGIN' : 'LOGOUT',
            'logged_at'   => now(),
        ]);

        // Dual-write into the administrator action log (the legacy
        // login_logs table keeps feeding the existing Audit Logs page).
        ActionLogger::log(
            ActionLogger::CATEGORY_AUTH,
            $event instanceof Login ? 'login' : 'logout',
            userId: $event->user?->getAuthIdentifier(),
        );
    }
}
