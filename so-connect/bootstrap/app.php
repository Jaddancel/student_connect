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
        $middleware->alias([
            'dashboard.access' => \App\Http\Middleware\EnsureDashboardAccess::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'role.member' => \App\Http\Middleware\EnsureMemberRole::class,
            'role.admin' => \App\Http\Middleware\EnsureOrganizationAdminRole::class,
            'role.president' => \App\Http\Middleware\EnsurePresidentRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
