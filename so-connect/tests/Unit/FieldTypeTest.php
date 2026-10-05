<?php

use App\Forms\FieldType;
use App\Forms\FieldKit;
use App\Forms\SystemFunction;

it('validates time and datetime fields against their input formats', function () {
    expect(FieldType::validationRules(FieldType::TIME, true))->toBe(['required', 'date_format:H:i'])
        ->and(FieldType::validationRules(FieldType::DATETIME, false))->toBe(['nullable', 'date_format:Y-m-d H:i']);
});

it('offers time and datetime in the palette but not age', function () {
    $palette = FieldType::paletteCatalog();

    expect($palette)->toHaveKeys([FieldType::TIME, FieldType::DATETIME])
        ->and($palette)->not->toHaveKey(FieldType::AGE)
        // age stays valid so existing forms keep re-saving and rendering
        ->and(FieldType::isValid(FieldType::AGE))->toBeTrue()
        ->and($palette[FieldType::DATETIME]['label'])->toBe('Date + Time');
});

it('hard-limits image uploads to jpeg, png and heic', function () {
    expect(FieldType::validationRules(FieldType::IMAGE, true))
        ->toBe(['required', 'file', 'mimes:jpeg,jpg,png,heic,heif', 'max:5120'])
        ->and(FieldType::validationRules(FieldType::IMAGE, true))->not->toContain('image');
});

it('hard-limits file uploads to jpeg, png, heic and pdf', function () {
    expect(FieldType::validationRules(FieldType::FILE, false))
        ->toBe(['nullable', 'file', 'mimes:jpeg,jpg,png,heic,heif,pdf', 'max:5120']);
});

it('intersects a field accept config with the allowlist', function () {
    // A narrower accept narrows the rule…
    expect(FieldType::effectiveUploadExtensions(FieldType::FILE, ['accept' => 'pdf']))->toBe(['pdf'])
        ->and(FieldType::effectiveUploadExtensions(FieldType::FILE, ['accept' => '.jpg, png']))->toBe(['jpeg', 'jpg', 'png'])
        // …but nothing outside the allowlist ever gets through,
        ->and(FieldType::effectiveUploadExtensions(FieldType::FILE, ['accept' => 'exe,docx']))
        ->toBe(['jpeg', 'jpg', 'png', 'heic', 'heif', 'pdf'])
        // and image fields can never accept a pdf.
        ->and(FieldType::effectiveUploadExtensions(FieldType::IMAGE, ['accept' => 'pdf,png']))->toBe(['png']);
});

it('renders an accept attribute mirroring the effective extensions', function () {
    expect(FieldType::uploadAcceptAttribute(FieldType::IMAGE))
        ->toBe('image/jpeg,image/png,image/heic,image/heif')
        ->and(FieldType::uploadAcceptAttribute(FieldType::FILE, ['accept' => 'pdf']))
        ->toBe('application/pdf');
});

it('offers the table field in every form palette', function () {
    expect(FieldType::paletteCatalog())->toHaveKey(FieldType::TABLE_INPUT)
        ->and(FieldType::catalog()[FieldType::TABLE_INPUT]['group'])->toBe('basic')
        ->and(FieldType::isSpecial(FieldType::TABLE_INPUT))->toBeFalse();
});

it('offers the search field in the palette', function () {
    expect(FieldType::paletteCatalog())->toHaveKey(FieldType::SEARCH)
        ->and(FieldType::isValid(FieldType::SEARCH))->toBeTrue()
        ->and(FieldType::catalog()[FieldType::SEARCH]['group'])->toBe('choice');
});

it('includes president and officer email fields in the organization registration kit', function () {
    expect(FieldType::catalog()[FieldType::NEW_PRESIDENT_EMAIL]['label'])->toBe('New President Email Field')
        ->and(FieldType::validationRules(FieldType::NEW_PRESIDENT_EMAIL, true))
        ->toBe(['required', 'string', 'email', 'max:255'])
        ->and(FieldType::paletteCatalog(SystemFunction::NEW_ORGANIZATION_REGISTRATION))
        ->toHaveKey(FieldType::NEW_PRESIDENT_EMAIL)
        ->toHaveKey(FieldType::NEW_OFFICER_EMAIL)
        ->and(FieldKit::required(SystemFunction::NEW_ORGANIZATION_REGISTRATION))
        ->toBe([
            'president_email' => FieldType::NEW_PRESIDENT_EMAIL,
            'officer_email' => FieldType::NEW_OFFICER_EMAIL,
            'organization_type' => FieldType::ORGANIZATION_TYPE_SELECT,
        ]);
});

    it('offers computed fields in the organization accreditation builder palette', function () {
        expect(FieldType::paletteCatalog(SystemFunction::ORG_ACCREDITATION))
        ->toHaveKey(FieldType::COMPUTED)
        ->toHaveKey(FieldType::ORG_SELECT)
        ->toHaveKey(FieldType::WORKPLAN_SELECT)
        ->and(FieldKit::required(SystemFunction::ORG_ACCREDITATION))->toBe([]);
    });

it('validates a sourced select/search against the scoped source values', function () {
    $opts = ['source' => 'organizations', 'source_values' => ['1', '2', '3']];

    // Both the sourced dropdown and the search field restrict to the resolved set.
    expect(FieldType::validationRules(FieldType::SELECT, true, $opts))->toBe(['required', 'in:1,2,3'])
        ->and(FieldType::validationRules(FieldType::SEARCH, false, $opts))->toBe(['nullable', 'in:1,2,3']);
});

it('rejects everything when a sourced field resolves to an empty scoped set', function () {
    // No authorized entries → in: with no values, which no non-empty value can satisfy.
    expect(FieldType::validationRules(FieldType::SEARCH, true, ['source' => 'organizations']))
        ->toBe(['required', 'in:']);
});

it('falls back to static options for a select with no source', function () {
    $opts = ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]];

    expect(FieldType::validationRules(FieldType::SELECT, true, $opts))->toBe(['required', 'in:a,b']);
});
