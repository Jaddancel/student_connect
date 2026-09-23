<?php

use App\Forms\FieldType;
use App\Services\DocumentVision\DocumentVisionResultNormalizer;

it('classifies paper support by field type', function () {
    expect(FieldType::paperSupport(FieldType::TEXT))->toBe(FieldType::PAPER_EXTRACT)
        ->and(FieldType::paperSupport(FieldType::NUMBER))->toBe(FieldType::PAPER_EXTRACT)
        ->and(FieldType::paperSupport(FieldType::DATE))->toBe(FieldType::PAPER_EXTRACT)
        ->and(FieldType::paperSupport(FieldType::TEXT_LIST))->toBe(FieldType::PAPER_EXTRACT)
        ->and(FieldType::paperSupport(FieldType::SIGNATURE))->toBe(FieldType::PAPER_SIGNATURE)
        ->and(FieldType::paperSupport(FieldType::PASSWORD))->toBe(FieldType::PAPER_DIGITAL_ONLY)
        ->and(FieldType::paperSupport(FieldType::FILE))->toBe(FieldType::PAPER_DIGITAL_ONLY)
        ->and(FieldType::paperSupport(FieldType::ID_SCAN))->toBe(FieldType::PAPER_DIGITAL_ONLY)
        ->and(FieldType::paperSupport(FieldType::COMPUTED))->toBe(FieldType::PAPER_DIGITAL_ONLY);
});

it('treats a static select as extractable but a sourced select as digital-only', function () {
    $static = ['options' => [['value' => 'acad', 'label' => 'Academic']]];
    // A sourced select carries options too, but its source (not the paper) is authoritative.
    $sourced = ['source' => 'organizations', 'options' => [['value' => 'acad', 'label' => 'Academic']]];

    expect(FieldType::paperSupport(FieldType::SELECT, $static))->toBe(FieldType::PAPER_EXTRACT)
        ->and(FieldType::paperSupport(FieldType::SELECT, $sourced))->toBe(FieldType::PAPER_DIGITAL_ONLY)
        ->and(FieldType::paperSupport(FieldType::SELECT, []))->toBe(FieldType::PAPER_DIGITAL_ONLY);
});

it('keeps only requested fields and never overwrites known values', function () {
    $normalizer = new DocumentVisionResultNormalizer;

    $fields = [
        ['key' => 'venue', 'type' => 'text', 'options' => null, 'page' => 0],
        ['key' => 'title', 'type' => 'text', 'options' => null, 'page' => 0],
    ];
    $raw = ['fields' => [
        'venue' => ['value' => 'Gym', 'confidence' => 0.9],
        'title' => ['value' => 'Overwrite', 'confidence' => 0.9],
        'ghost' => ['value' => 'ignored', 'confidence' => 1.0],
    ]];

    $result = $normalizer->normalize($fields, $raw, ['title' => 'Kept']);

    expect($result['values'])->toBe(['venue' => 'Gym'])
        ->and($result['values'])->not->toHaveKey('title')
        ->and($result['values'])->not->toHaveKey('ghost');
});

it('maps a select label to its canonical value and rejects unknown options', function () {
    $normalizer = new DocumentVisionResultNormalizer;

    $fields = [[
        'key' => 'committee', 'type' => 'select', 'page' => 0,
        'options' => [['value' => 'acad', 'label' => 'Academic'], ['value' => 'sports', 'label' => 'Sports']],
    ]];

    $matched = $normalizer->normalize($fields, ['fields' => ['committee' => ['value' => 'Academic']]]);
    $unknown = $normalizer->normalize($fields, ['fields' => ['committee' => ['value' => 'Music']]]);

    expect($matched['values'])->toBe(['committee' => 'acad'])
        ->and($unknown['values'])->toBe([])
        ->and($unknown['unresolved'])->toContain('committee');
});

it('reports signatures as presence and bounds only, never as text', function () {
    $normalizer = new DocumentVisionResultNormalizer;

    $fields = [['key' => 'adviser_sig', 'type' => 'signature', 'page' => 1]];
    $raw = ['fields' => ['adviser_sig' => ['present' => true, 'confidence' => 0.8, 'bounds' => [0.1, 0.2, 0.5, 0.4]]]];

    $result = $normalizer->normalize($fields, $raw);

    expect($result['values'])->toBe([])
        ->and($result['signatures']['adviser_sig']['present'])->toBeTrue()
        ->and($result['signatures']['adviser_sig']['bounds'])->toBe([0.1, 0.2, 0.5, 0.4]);
});

it('discards malformed bounding boxes', function () {
    $normalizer = new DocumentVisionResultNormalizer;

    $fields = [['key' => 'sig', 'type' => 'signature', 'page' => 0]];

    $inverted = $normalizer->normalize($fields, ['fields' => ['sig' => ['present' => true, 'bounds' => [0.9, 0.9, 0.1, 0.1]]]]);
    $outOfRange = $normalizer->normalize($fields, ['fields' => ['sig' => ['present' => true, 'bounds' => [0, 0, 1.5, 1]]]]);
    $wrongCount = $normalizer->normalize($fields, ['fields' => ['sig' => ['present' => true, 'bounds' => [0, 0, 1]]]]);

    expect($inverted['signatures']['sig']['bounds'])->toBeNull()
        ->and($outOfRange['signatures']['sig']['bounds'])->toBeNull()
        ->and($wrongCount['signatures']['sig']['bounds'])->toBeNull();
});

it('clamps confidence into the unit interval', function () {
    $normalizer = new DocumentVisionResultNormalizer;

    $fields = [['key' => 'venue', 'type' => 'text', 'page' => 0]];
    $result = $normalizer->normalize($fields, ['fields' => ['venue' => ['value' => 'Gym', 'confidence' => 1.7]]]);

    expect($result['confidence']['venue'])->toBe(1.0);
});
