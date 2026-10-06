<?php

use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function padCapture(): string
{
    $image = imagecreatetruecolor(600, 200);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    inkStroke($image, 120, 70, 350, 60, imagecolorallocate($image, 15, 15, 25));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function asDataUrl(string $binary): string
{
    return 'data:image/png;base64,'.base64_encode($binary);
}

function extractedSize(string $png): array
{
    $image = imagecreatefromstring($png);

    return [imagesx($image), imagesy($image)];
}

it('crops a pad capture to its ink', function () {
    $png = SignatureImage::extractInk(padCapture());

    expect($png)->not->toBeNull();

    [$width, $height] = extractedSize($png);
    // The stroke is 350x60 inside a 600x200 canvas, plus padding.
    expect($width)->toBeLessThan(400)->toBeGreaterThan(340)
        ->and($height)->toBeLessThan(110)->toBeGreaterThan(55);
});

it('keeps only the ink from a photo of paper, discarding the page', function () {
    $png = SignatureImage::extractInk(paperPhoto());

    expect($png)->not->toBeNull();

    [$width, $height] = extractedSize($png);
    // ~405px of stroke in a 900px-wide photo: the page is gone, not the ink.
    expect($width)->toBeLessThan(460)->toBeGreaterThan(380)
        ->and($height)->toBeLessThan(130);
});

it('makes the ink transparent so no background is stored', function () {
    $png = SignatureImage::extractInk(padCapture());
    $image = imagecreatefromstring($png);

    // The extreme corner is outside any stroke, so it must be fully transparent.
    $corner = imagecolorat($image, 0, 0);
    expect(($corner >> 24) & 0x7F)->toBe(127);

    // …and some pixel in the middle band is opaque ink.
    $opaque = 0;
    for ($x = 0; $x < imagesx($image); $x++) {
        for ($y = 0; $y < imagesy($image); $y++) {
            if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) === 0) {
                $opaque++;
            }
        }
    }
    expect($opaque)->toBeGreaterThan(100);
});

it('drops a shadow band that runs off the edge of the frame', function () {
    $image = imagecreatetruecolor(1000, 700);
    imagefill($image, 0, 0, imagecolorallocate($image, 235, 233, 228));
    // A hard-edged shadow down the left side, touching top and bottom.
    imagefilledrectangle($image, 0, 0, 60, 699, imagecolorallocate($image, 55, 52, 50));
    inkStroke($image, 350, 300, 400, 90, imagecolorallocate($image, 20, 25, 60), 5);
    ob_start();
    imagejpeg($image, null, 88);
    $png = SignatureImage::extractInk((string) ob_get_clean());

    expect($png)->not->toBeNull();

    [$width, $height] = extractedSize($png);
    // Only the 400x90 stroke survives — not the full 1000x700 frame.
    expect($width)->toBeLessThan(460)
        ->and($height)->toBeLessThan(140);
});

it('rejects an underexposed photo instead of storing the whole frame', function () {
    expect(SignatureImage::extractInk(flatImage(800, 600, 40, 42, 48, 8)))->toBeNull();
});

it('rejects a blank page', function () {
    expect(SignatureImage::extractInk(flatImage(800, 600, 248, 248, 245, 4)))->toBeNull();
});

it('rejects an undecodable image', function () {
    expect(SignatureImage::extractInk('not an image at all'))->toBeNull();
});

it('leaves an already-extracted signature unchanged on a second pass', function () {
    $once = SignatureImage::extractInk(padCapture());
    $twice = SignatureImage::extractInk($once);

    expect($twice)->not->toBeNull()
        ->and(extractedSize($twice))->toBe(extractedSize($once));
});

it('stores the extracted ink, never the uploaded photo', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $photo = paperPhoto();
    $path = SignatureImage::storeDataUrl(asDataUrl($photo), 'signatures/test');

    expect($path)->not->toBeNull();

    $stored = Storage::disk('public')->get($path);
    expect($stored)->not->toBe($photo)
        ->and(strlen($stored))->toBeLessThan(strlen($photo));

    // Stored as a transparent PNG, whatever was uploaded.
    expect(str_starts_with($stored, "\x89PNG"))->toBeTrue();
    [$width] = extractedSize($stored);
    expect($width)->toBeLessThan(900);
});

it('stores nothing when the image holds no signature', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    expect(SignatureImage::storeDataUrl(asDataUrl(flatImage(400, 300, 250, 250, 248)), 'signatures/test'))->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
