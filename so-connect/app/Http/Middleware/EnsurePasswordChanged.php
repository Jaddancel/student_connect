<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->force_password_change
            && ! $request->routeIs('password.change', 'password.change.update', 'logout')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
