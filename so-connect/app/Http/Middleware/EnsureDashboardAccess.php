<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    public function handle(Request $request, Closure $next, string $dashboard): Response
    {
        $user = $request->user();

        if (! $user || ! Gate::forUser($user)->allows('access-dashboard', $dashboard)) {
            return redirect()->route('officer-dashboard');
        }

        return $next($request);
    }
}
