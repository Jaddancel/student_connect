<?php

use App\Listeners\LogAuthActivity;
use App\Models\ActionLog;
use App\Models\LoginLog;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Auth\Events\Logout as LogoutEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('logs login events to login_logs and action_logs', function () {
    $user = recordsUser(3);

    (new LogAuthActivity())->handle(new LoginEvent('web', $user, true));

    $loginLog = LoginLog::query()->latest('log_id')->first();
    $actionLog = ActionLog::query()->latest('action_log_id')->first();

    expect($loginLog)->not->toBeNull()
        ->and((int) $loginLog->user_id)->toBe((int) $user->getKey())
        ->and($loginLog->interaction)->toBe('LOGIN')
        ->and($actionLog)->not->toBeNull()
        ->and((int) $actionLog->user_id)->toBe((int) $user->getKey())
        ->and($actionLog->category)->toBe('auth')
        ->and($actionLog->action)->toBe('login');
});

it('logs logout events to login_logs and action_logs', function () {
    $user = recordsUser(3);

    (new LogAuthActivity())->handle(new LogoutEvent('web', $user));

    $loginLog = LoginLog::query()->latest('log_id')->first();
    $actionLog = ActionLog::query()->latest('action_log_id')->first();

    expect($loginLog)->not->toBeNull()
        ->and((int) $loginLog->user_id)->toBe((int) $user->getKey())
        ->and($loginLog->interaction)->toBe('LOGOUT')
        ->and($actionLog)->not->toBeNull()
        ->and((int) $actionLog->user_id)->toBe((int) $user->getKey())
        ->and($actionLog->category)->toBe('auth')
        ->and($actionLog->action)->toBe('logout');
});
