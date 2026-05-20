<?php

namespace App\Listeners;

use App\Models\LoginLog;
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
    }
}
