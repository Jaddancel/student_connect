<?php

use App\Forms\FieldType;

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
