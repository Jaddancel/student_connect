<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', \App\Http\Middleware\EnsureSystemInitialized::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordChanged::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureOrganizationAccredited::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\PreventBackHistory::class);

        $middleware->redirectGuestsTo(fn () => route('home'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'dashboard.access'   => \App\Http\Middleware\EnsureDashboardAccess::class,
            'superadmin'         => \App\Http\Middleware\EnsureSuperAdmin::class,
            'admin'              => \App\Http\Middleware\EnsureAdmin::class,
            'admin.or.superadmin'=> \App\Http\Middleware\EnsureAdminOrSuperAdmin::class,
            'role.admin'         => \App\Http\Middleware\EnsureOrganizationAdminRole::class,
            'role.president'     => \App\Http\Middleware\EnsurePresidentRole::class,
            'president.or.admin' => \App\Http\Middleware\EnsurePresidentOrAdmin::class,
            'role.officer'       => \App\Http\Middleware\EnsureOfficerRole::class,
            'officer.or.admin'   => \App\Http\Middleware\EnsureOfficerOrAdmin::class,
            'password.changed'   => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
