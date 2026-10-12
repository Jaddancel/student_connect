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
