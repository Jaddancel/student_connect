<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds hardening HTTP response headers flagged by the OWASP ZAP scan:
 *  - Content-Security-Policy        (CSP Header Not Set)
 *  - X-Frame-Options                (Missing Anti-clickjacking Header)
 *  - X-Content-Type-Options         (X-Content-Type-Options Header Missing)
 *  - Referrer-Policy / Permissions-Policy (defence in depth)
 * and strips the framework/PHP fingerprint headers
 *  - X-Powered-By / Server          (Server Leaks Information via "X-Powered-By")
 *  - Strict-Transport-Security      (HTTPS responses only)
 * and empties redirect bodies (Big Redirect Detected).
 *
 * Registered as GLOBAL middleware (bootstrap/app.php) rather than on the `web`
 * group, so it also covers 404s for unmatched routes and the redirects issued
 * by `auth` (which runs ahead of group middleware by priority).
 *
 * The strict CSP (config security.csp_strict) is nonce-based with no
 * 'unsafe-inline' / 'unsafe-eval' for scripts or <style> elements (ZAP: CSP
 * script-src/style-src unsafe-inline, script-src unsafe-eval). Views must:
 *  - mark every inline <script>/<style> with @nonce,
 *  - use no inline on*="" event handler attributes,
 *  - keep Alpine expressions within the CSP build's syntax
 *    (checked by `npm run check:csp`).
 */
class SecurityHeaders
{
    /**
     * External origins the application is allowed to load resources from.
     */
    private const CDN_HOSTS = [
        'https://cdn.jsdelivr.net',
        'https://use.fontawesome.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the view renders so @vite tags and @nonce pick it up.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // Routes that opt out with ->withoutMiddleware(SecurityHeaders::class)
        // (the OnlyOffice callbacks/plugin, framed by the Document Server) set
        // their own framing policy. Global middleware still runs for them, so
        // honour the exclusion here.
        $route = $request->route();
        if ($route instanceof Route && in_array(self::class, $route->excludedMiddleware(), true)) {
            $this->stripFingerprint($response);

            return $response;
        }

        $cdn = implode(' ', self::CDN_HOSTS);

        // The form builder's printed-template step loads the OnlyOffice editor
        // bundle and frames the editor, both from the Document Server's origin.
        // A different port is a different origin, so 'self' does not cover it
        // and the editor would be blocked outright.
        $editor = $this->editorOrigin();

        // Strict policy: no 'unsafe-inline' / 'unsafe-eval' for scripts or
        // <style> elements. Inline <script>/<style> blocks carry the
        // per-request nonce (@nonce), inline event handlers are not used, and
        // Alpine runs from its CSP build. style="" attributes stay allowed
        // (style-src-attr): they cannot execute script, and Alpine (x-show,
        // :style), Font Awesome, ApexCharts and flatpickr all set them.
        $strict = $this->policy($cdn, $editor, [
            "script-src 'self' 'nonce-{$nonce}' {$cdn} {$editor}",
            "style-src 'self' 'nonce-{$nonce}' {$cdn} {$editor}",
            "style-src-attr 'unsafe-inline'",
        ]);

        if (config('security.csp_strict')) {
            $response->headers->set('Content-Security-Policy', $strict);
        } else {
            // Until every view is converted (`npm run check:csp` reports 0),
            // enforce the transitional policy and only report strict-mode
            // violations, so nothing is blocked. A nonce must not appear in the
            // enforced policy: browsers ignore 'unsafe-inline' next to one.
            $response->headers->set('Content-Security-Policy', $this->policy($cdn, $editor, [
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$cdn} {$editor}",
                "style-src 'self' 'unsafe-inline' {$cdn} {$editor}",
            ]));
            $response->headers->set('Content-Security-Policy-Report-Only', $strict);
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Browsers follow the Location header and never render a redirect's
        // body, so Symfony's default HTML "Redirecting to…" page only leaks.
        if ($response instanceof RedirectResponse) {
            $response->setContent('');
        }

        $this->stripFingerprint($response);

        return $response;
    }

    /**
     * Full policy string with the given script-src/style-src (and optional
     * style-src-attr) directives.
     *
     * @param  array{0: string, 1: string, 2?: string}  $scriptAndStyle
     */
    private function policy(string $cdn, string $editor, array $scriptAndStyle): string
    {
        [$scriptSrc, $styleSrc] = $scriptAndStyle;
        $styleAttr = $scriptAndStyle[2] ?? null;

        return implode('; ', array_filter([
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: blob: {$cdn} {$editor}",
            $scriptSrc,
            // HEIC previews are decoded locally in a blob-backed web worker.
            "worker-src 'self' blob:",
            $styleSrc,
            $styleAttr,
            "font-src 'self' data: {$cdn} {$editor}",
            "connect-src 'self' {$cdn} {$editor}",
            $editor !== '' ? "frame-src 'self' {$editor}" : null,
        ]));
    }

    /**
     * Strip fingerprinting headers that disclose the server/framework.
     */
    private function stripFingerprint(Response $response): void
    {
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        header_remove('X-Powered-By');
    }

    /**
     * Scheme+host+port of the OnlyOffice Document Server, or '' when the
     * editor is not configured (in which case the policy stays as it was).
     */
    private function editorOrigin(): string
    {
        $url = trim((string) config('onlyoffice.public_url', ''));

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
