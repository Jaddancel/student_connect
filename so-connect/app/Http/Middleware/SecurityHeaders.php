<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds hardening HTTP response headers flagged by the OWASP ZAP scan:
 *  - Content-Security-Policy        (CSP Header Not Set)
 *  - X-Frame-Options                (Missing Anti-clickjacking Header)
 *  - X-Content-Type-Options         (X-Content-Type-Options Header Missing)
 *  - Referrer-Policy / Permissions-Policy (defence in depth)
 * and strips the framework/PHP fingerprint headers
 *  - X-Powered-By / Server          (Server Leaks Information via "X-Powered-By")
 *
 * Note: the CSP intentionally keeps 'unsafe-inline' for scripts/styles because
 * the app uses inline <script> blocks and inline event handlers. It still
 * meaningfully restricts default/object/base/frame sources and pins the set of
 * external origins the app is allowed to load from.
 */
class SecurityHeaders
{
    /**
     * External origins the application is allowed to load resources from.
     */
    private const CDN_HOSTS = [
        'https://cdn.jsdelivr.net',
        'https://cdn.startbootstrap.com',
        'https://use.fontawesome.com',
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $cdn = implode(' ', self::CDN_HOSTS);

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: blob: {$cdn}",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$cdn}",
            "style-src 'self' 'unsafe-inline' {$cdn}",
            "font-src 'self' data: {$cdn}",
            "connect-src 'self' {$cdn}",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Strip fingerprinting headers that disclose the server/framework.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        header_remove('X-Powered-By');

        return $response;
    }
}
