<?php

use App\Services\ManualForm\SentinelPdfLocator;

/** A `pdftotext -bbox` XHTML page with the given `<word>` rows. */
function bboxXhtml(string $words, float $w = 600.0, float $h = 800.0): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<html xmlns="http://www.w3.org/1999/xhtml"><head></head><body>'
        .'<doc><page width="'.$w.'" height="'.$h.'">'.$words.'</page></doc>'
        .'</body></html>';
}

function bboxWord(string $text, float $x1, float $y1, float $x2, float $y2): string
{
    return '<word xMin="'.$x1.'" yMin="'.$y1.'" xMax="'.$x2.'" yMax="'.$y2.'">'.$text.'</word>';
}

it('parses pdftotext -bbox words into normalized coordinates', function () {
    $locator = app(SentinelPdfLocator::class);

    $xhtml = bboxXhtml(
        bboxWord('MFX0000XFM', 60, 80, 180, 100)
        .bboxWord('President', 60, 160, 200, 180),
        600,
        800,
    );

    $pages = $locator->parseWords($xhtml);

    expect($pages)->toHaveKey(0)
        ->and($pages[0]['words'])->toHaveCount(2)
        ->and($pages[0]['words'][0]['text'])->toBe('MFX0000XFM')
        ->and($pages[0]['words'][0]['x1'])->toBe(0.1)   // 60/600
        ->and($pages[0]['words'][0]['y1'])->toBe(0.1)   // 80/800
        ->and($pages[0]['words'][0]['x2'])->toBe(0.3);  // 180/600
});

it('builds a signature region from the anchor down to the text below it', function () {
    $locator = app(SentinelPdfLocator::class);

    // Signature marker high in a column; the printed name sits below it.
    $anchor = ['page' => 0, 'bounds' => [0.30, 0.40, 0.45, 0.43]];
    $words = [
        ['x1' => 0.30, 'y1' => 0.40, 'x2' => 0.45, 'y2' => 0.43], // the marker itself
        ['x1' => 0.31, 'y1' => 0.52, 'x2' => 0.60, 'y2' => 0.55], // name below, same column
        ['x1' => 0.80, 'y1' => 0.41, 'x2' => 0.95, 'y2' => 0.44], // other column, ignored
    ];

    $region = $locator->signatureRegion($anchor, $words, [0.25, 0.65], 0.06);

    // Spans the column horizontally and stops just above the name below.
    expect($region[0])->toBe(0.25)
        ->and($region[2])->toBe(0.65)
        ->and($region[1])->toBeLessThan(0.40)
        ->and($region[3])->toBeGreaterThan(0.43)
        ->and($region[3])->toBeLessThan(0.52);
});

it('falls back to a fixed band when nothing sits below the signature', function () {
    $locator = app(SentinelPdfLocator::class);

    $anchor = ['page' => 0, 'bounds' => [0.30, 0.80, 0.45, 0.83]];
    $region = $locator->signatureRegion($anchor, [], [0.25, 0.65], 0.06);

    // No word below → anchor bottom + fallback band.
    expect($region[3])->toBe(round(0.83 + 0.06, 4));
});

it('generates distinct single-word markers per field ordinal', function () {
    $locator = app(SentinelPdfLocator::class);

    expect($locator->token(0))->toBe('MFX0000XFM')
        ->and($locator->token(12))->toBe('MFX0012XFM')
        ->and($locator->token(0))->not->toBe($locator->token(1));
});
