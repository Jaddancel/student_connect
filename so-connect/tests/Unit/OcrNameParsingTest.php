<?php

use App\Services\OcrClient;

/*
 * Pure unit coverage for the OCR post-processing that turns raw ID text into
 * usable form values: PH-style name splitting and printed-date normalization.
 */

// ----- splitFullName: comma form (`LAST, FIRST [MIDDLE-INITIAL] [SUFFIX]`) -----

it('keeps a compound first name together in the comma form', function () {
    expect(OcrClient::splitFullName('DELA CRUZ, JUAN MIGUEL'))->toBe([
        'first_name' => 'JUAN MIGUEL',
        'last_name' => 'DELA CRUZ',
    ]);
});

it('detects a trailing middle initial in the comma form', function () {
    expect(OcrClient::splitFullName('DELA CRUZ, JUAN MIGUEL P.'))->toBe([
        'first_name' => 'JUAN MIGUEL',
        'middle_name' => 'P.',
        'last_name' => 'DELA CRUZ',
    ]);
});

it('handles a plain two-part comma name', function () {
    expect(OcrClient::splitFullName('SANTOS, MARIA'))->toBe([
        'first_name' => 'MARIA',
        'last_name' => 'SANTOS',
    ]);
});

it('keeps a suffix after the surname segment with the surname', function () {
    expect(OcrClient::splitFullName('REYES JR., JUAN M.'))->toBe([
        'first_name' => 'JUAN',
        'middle_name' => 'M.',
        'last_name' => 'REYES JR.',
    ]);
});

it('moves a suffix trailing the given names onto the surname', function () {
    expect(OcrClient::splitFullName('REYES, JUAN M. JR.'))->toBe([
        'first_name' => 'JUAN',
        'middle_name' => 'M.',
        'last_name' => 'REYES JR.',
    ]);
});

it('treats a roman-numeral suffix like JR', function () {
    expect(OcrClient::splitFullName('CRUZ, JUAN CARLO III'))->toBe([
        'first_name' => 'JUAN CARLO',
        'last_name' => 'CRUZ III',
    ]);
});

it('does not treat a lone initial after the comma as a middle name', function () {
    expect(OcrClient::splitFullName('CRUZ, J.'))->toBe([
        'first_name' => 'J.',
        'last_name' => 'CRUZ',
    ]);
});

// ----- splitFullName: no-comma form (`FIRST [MIDDLE-INITIAL] LAST [SUFFIX]`) -----

it('absorbs surname particles into the last name', function () {
    expect(OcrClient::splitFullName('JUAN MIGUEL DELA CRUZ'))->toBe([
        'first_name' => 'JUAN MIGUEL',
        'last_name' => 'DELA CRUZ',
    ]);
});

it('handles multi-particle surnames', function () {
    expect(OcrClient::splitFullName('JOHN PAUL DELOS SANTOS'))->toBe([
        'first_name' => 'JOHN PAUL',
        'last_name' => 'DELOS SANTOS',
    ]);
});

it('detects a middle initial before the surname', function () {
    expect(OcrClient::splitFullName('JUAN P. DELA CRUZ'))->toBe([
        'first_name' => 'JUAN',
        'middle_name' => 'P.',
        'last_name' => 'DELA CRUZ',
    ]);
});

it('does not mistake an abbreviated first name for an initial', function () {
    expect(OcrClient::splitFullName('MA. CRISTINA SAN JUAN'))->toBe([
        'first_name' => 'MA. CRISTINA',
        'last_name' => 'SAN JUAN',
    ]);
});

it('keeps a no-comma suffix with the surname', function () {
    expect(OcrClient::splitFullName('JUAN M. REYES JR.'))->toBe([
        'first_name' => 'JUAN',
        'middle_name' => 'M.',
        'last_name' => 'REYES JR.',
    ]);
});

it('returns a single token as the first name', function () {
    expect(OcrClient::splitFullName('MADONNA'))->toBe(['first_name' => 'MADONNA']);
});

it('handles two plain tokens as first and last', function () {
    expect(OcrClient::splitFullName('JUAN CRUZ'))->toBe([
        'first_name' => 'JUAN',
        'last_name' => 'CRUZ',
    ]);
});

it('collapses messy whitespace and returns nothing for blank input', function () {
    expect(OcrClient::splitFullName("  JUAN \t MIGUEL   DELA  CRUZ "))->toBe([
        'first_name' => 'JUAN MIGUEL',
        'last_name' => 'DELA CRUZ',
    ])->and(OcrClient::splitFullName('   '))->toBe([]);
});

// ----- normalizeDate -----

it('parses the printed Month Day, Year layout', function () {
    expect(OcrClient::normalizeDate('JANUARY 5, 2003'))->toBe('2003-01-05');
});

it('parses month-name variants', function () {
    expect(OcrClient::normalizeDate('January 5 2003'))->toBe('2003-01-05')
        ->and(OcrClient::normalizeDate('Jan 5, 2003'))->toBe('2003-01-05')
        ->and(OcrClient::normalizeDate('JAN. 5, 2003'))->toBe('2003-01-05')
        ->and(OcrClient::normalizeDate('5 January 2003'))->toBe('2003-01-05');
});

it('parses slashed and dashed numeric dates as month-first', function () {
    expect(OcrClient::normalizeDate('01/05/2003'))->toBe('2003-01-05')
        ->and(OcrClient::normalizeDate('1/5/2003'))->toBe('2003-01-05')
        ->and(OcrClient::normalizeDate('01-05-2003'))->toBe('2003-01-05');
});

it('passes an already-ISO date through unchanged', function () {
    expect(OcrClient::normalizeDate('2003-01-05'))->toBe('2003-01-05');
});

it('returns null for unreadable text', function () {
    expect(OcrClient::normalizeDate('not a date'))->toBeNull()
        ->and(OcrClient::normalizeDate(''))->toBeNull();
});
