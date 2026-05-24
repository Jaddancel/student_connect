<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        if ((int) $user->user_type !== 2) {
            abort(403, 'Only administrators can access this page.');
        }

        return $next($request);
    }
}
