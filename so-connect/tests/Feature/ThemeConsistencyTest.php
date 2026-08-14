<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The light/dark choice lives in one localStorage key and is applied by one
 * partial. These tests pin the wiring: every user-facing surface must boot the
 * shared theme script and expose a control, otherwise a page silently ignores
 * the choice made elsewhere (the bug this suite was written for: the landing
 * page stayed light no matter what the dashboard was set to).
 */

/** Views that render a full page for a human. Excludes PDF/print/mail templates. */
function themedPageViews(): array
{
    return [
        ...glob(resource_path('views/layouts/*.blade.php')),
        ...glob(resource_path('views/landingPage/*.blade.php')),
    ];
}

it('boots the shared theme partial on every full-page view', function () {
    $missing = [];

    foreach (themedPageViews() as $path) {
        $source = file_get_contents($path);

        // Only views that own a <html> document need to boot the theme.
        if (! str_contains($source, '<html')) {
            continue;
        }

        if (! str_contains($source, "@include('layouts.partials.theme-boot')")) {
            $missing[] = basename($path);
        }
    }

    expect($missing)->toBe([]);
});

it('applies the saved theme to <html> before the body renders', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $headEnd = strpos($html, '</head>');
    $bootAt = strpos($html, "localStorage.getItem(KEY)");

    expect($bootAt)->not->toBeFalse('the theme bootstrap script is missing');
    expect($bootAt)->toBeLessThan($headEnd, 'the theme bootstrap must run inside <head>, before first paint');
});

it('exposes a theme toggle on the public landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme-toggle', escape: false)
        ->assertSee('window.appTheme.toggle()', escape: false);
});

it('exposes a theme toggle on an organization feed', function () {
    $organizationId = DB::table('organizations')->value('organization_id');

    if ($organizationId === null) {
        $organizationId = DB::table('organizations')->insertGetId([
            'organization_type' => 1,
        ]);
    }

    $this->get("/organizations/{$organizationId}")
        ->assertOk()
        ->assertSee('data-theme-toggle', escape: false);
});

it('exposes a theme toggle on the dashboard', function () {
    $superadmin = User::where('user_type', User::TYPE_SUPERADMIN)->firstOrFail();

    $this->actingAs($superadmin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-theme-toggle', escape: false);
});

it('falls back to the OS colour scheme when nothing is saved', function () {
    $boot = file_get_contents(resource_path('views/layouts/partials/theme-boot.blade.php'));

    expect($boot)->toContain('prefers-color-scheme: dark');
    // The fallback must be consulted, not just declared.
    expect($boot)->toContain('stored() ?? system()');
});

/**
 * A saved choice that agrees with the system is indistinguishable from having
 * no choice at all — except that it silently outranks every later system
 * change. Storing it is what left the dashboard dark on a light browser, so the
 * boot script must clear it on load and refuse to write it back.
 */
it('never keeps a saved choice that agrees with the system', function () {
    $boot = file_get_contents(resource_path('views/layouts/partials/theme-boot.blade.php'));

    expect($boot)->toContain('releaseRedundantChoice');
    // Cleared on load, so pins written by earlier builds do not survive.
    expect($boot)->toMatch('/releaseRedundantChoice\(\);\s*\n\s*apply\(read\(\)\);/');
    // And never written in the first place.
    expect($boot)->toMatch('/if \(next === system\(\)\) \{\s*\n\s*localStorage\.removeItem\(KEY\);/');
});

it('reads and writes a single localStorage key everywhere', function () {
    $boot = file_get_contents(resource_path('views/layouts/partials/theme-boot.blade.php'));

    expect($boot)->toContain("const KEY = 'theme'");

    // No other view may hand-roll its own theme storage or class toggling.
    $offenders = [];

    foreach (array_merge(themedPageViews(), glob(resource_path('views/components/*.blade.php'))) as $path) {
        if (str_ends_with($path, 'theme-boot.blade.php')) {
            continue;
        }

        $source = file_get_contents($path);

        if (str_contains($source, "localStorage.getItem('theme')")
            || str_contains($source, "localStorage.setItem('theme'")) {
            $offenders[] = basename($path);
        }
    }

    expect($offenders)->toBe([]);
});
