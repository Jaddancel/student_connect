<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Draw a signature-like wave into a GD image. */
function inkStroke($image, int $x, int $y, int $w, int $h, int $colour, int $thickness = 3): void
{
    imagesetthickness($image, $thickness);
    for ($i = 0; $i < $w; $i += 3) {
        $y1 = $y + (int) ($h / 2 + ($h / 2 - 2) * sin($i / 14));
        $y2 = $y + (int) ($h / 2 + ($h / 2 - 2) * sin(($i + 3) / 14));
        imageline($image, $x + $i, $y1, $x + $i + 3, $y2, $colour);
    }
}

/**
 * A photographed signature on paper: uneven lighting, sensor noise, JPEG
 * artefacts — the case the extractor exists for.
 */
function paperPhoto(int $width = 900, int $height = 700): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $shade = 205 - (int) (55 * ($x / $width)) - (int) (25 * ($y / $height)) + random_int(-6, 6);
            $shade = max(0, min(255, $shade));
            imagesetpixel($image, $x, $y, imagecolorallocate($image, min(255, $shade + 12), min(255, $shade + 6), $shade));
        }
    }
    inkStroke(
        $image,
        (int) ($width * 0.25), (int) ($height * 0.4),
        (int) ($width * 0.45), 90,
        imagecolorallocate($image, 30, 35, 90), 5,
    );

    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

/** A featureless image — a blank page, or a frame too dark to read. */
function flatImage(int $width, int $height, int $r, int $g, int $b, int $noise = 0): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            imagesetpixel($image, $x, $y, imagecolorallocate(
                $image,
                min(255, $r + random_int(0, $noise)),
                min(255, $g + random_int(0, $noise)),
                min(255, $b + random_int(0, $noise)),
            ));
        }
    }

    ob_start();
    imagejpeg($image, null, 88);

    return (string) ob_get_clean();
}

/**
 * A drawn signature as a PNG data-URL. Signatures are extracted (not stored
 * as-is), so tests need a real image with legible ink in it — `$seed` varies the
 * stroke so two captures differ.
 */
function signatureDataUrl(int $seed = 0): string
{
    $image = imagecreatetruecolor(600, 200);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    $ink = imagecolorallocate($image, 15, 15, 25);
    imagesetthickness($image, 3);

    for ($i = 0; $i < 350; $i += 3) {
        $wave = 26 + ($seed % 5) * 4;
        $y1 = 100 + (int) (28 * sin(($i + $seed * 7) / $wave));
        $y2 = 100 + (int) (28 * sin(($i + 3 + $seed * 7) / $wave));
        imageline($image, 120 + $i, $y1, 120 + $i + 3, $y2, $ink);
    }

    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * Create a user (with profile) of the given user_type for records/admin tests.
 */
function recordsUser(int $type, array $profileAttributes = []): \App\Models\User
{
    $profile = \App\Models\Profile::query()->create(array_merge([
        'first_name' => 'Rec',
        'last_name' => 'User'.\Illuminate\Support\Str::random(6),
        'middle_name' => 'T',
        'occupation' => 'Staff',
    ], $profileAttributes));

    return \App\Models\User::query()->create([
        'user_email' => 'rec'.\Illuminate\Support\Str::random(8).'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

/**
 * Create an organization with a detail name for records tests.
 */
function recordsOrganization(string $name, ?string $initials = null): \App\Models\Organization
{
    $detail = \App\Models\Organization\OrganizationDetail::query()->create([
        'name' => $name,
        'initials' => $initials,
        'detail_text' => $name.' description',
    ]);

    return \App\Models\Organization::query()->create([
        'detail' => $detail->getKey(),
        'organization_type' => 1,
    ]);
}
