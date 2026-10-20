<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

uses(TestCase::class);

it('allows HEIC decoder workers without widening script or object sources', function () {
    config(['onlyoffice.public_url' => '']);

    $response = (new SecurityHeaders)->handle(
        Request::create('/'),
        fn () => new Response,
    );

    $policy = $response->headers->get('Content-Security-Policy');
    $directives = explode('; ', $policy);

    expect($directives)->toContain("worker-src 'self' blob:")
        ->toContain("object-src 'none'")
        ->and(collect($directives)->first(fn ($directive) => str_starts_with($directive, 'script-src ')))
        ->not->toContain('blob:');
});

it('empties redirect bodies so they cannot leak page content', function () {
    $response = (new SecurityHeaders)->handle(
        Request::create('/dashboard'),
        fn () => new \Symfony\Component\HttpFoundation\RedirectResponse('http://localhost/'),
    );

    expect($response->getContent())->toBe('')
        ->and($response->headers->get('Location'))->toBe('http://localhost/')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('sends HSTS only over HTTPS', function () {
    $handle = fn (string $url) => (new SecurityHeaders)->handle(Request::create($url), fn () => new Response);

    expect($handle('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and($handle('http://localhost/')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('does not allow arbitrary CORS origins by default', function () {
    expect(config('cors.allowed_origins'))->not->toContain('*');
});

it('rejects SQL metacharacters in the scanned student id', function () {
    $rules = \App\Forms\FieldType::validationRules(\App\Forms\FieldType::ID_SCAN, true);

    expect(validator(['student_id' => "ZAP' OR '1'='1' -- "], ['student_id' => $rules])->fails())->toBeTrue()
        ->and(validator(['student_id' => '2021012345'], ['student_id' => $rules])->passes())->toBeTrue();
});

it('enforces a nonce-based policy without unsafe-inline or unsafe-eval for scripts and style elements', function () {
    config(['security.csp_strict' => true, 'onlyoffice.public_url' => '']);

    $response = (new SecurityHeaders)->handle(Request::create('/'), fn () => new Response);
    $directives = collect(explode('; ', $response->headers->get('Content-Security-Policy')))
        ->mapWithKeys(fn ($d) => [strtok($d, ' ') => $d]);
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();

    expect($directives['script-src'])->toContain("'nonce-{$nonce}'")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval')
        ->and($directives['style-src'])->toContain("'nonce-{$nonce}'")
        ->not->toContain('unsafe-inline')
        ->and($directives['style-src-attr'])->toBe("style-src-attr 'unsafe-inline'")
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});
