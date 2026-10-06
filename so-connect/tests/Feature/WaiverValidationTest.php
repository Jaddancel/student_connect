<?php

use App\Services\WaiverValidationService;
use App\Support\ZonePayloadValidator;
use Illuminate\Validation\ValidationException;

it('validates and normalizes zone payloads including stamp zones', function () {
    $clean = ZonePayloadValidator::validate([
        ['name' => 'Participant', 'type' => 'text', 'x' => 10.4, 'y' => 20, 'w' => 100, 'h' => 30],
        ['name' => 'Seal', 'type' => 'stamp', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 50, 'field' => 'stamp'],
    ]);

    expect($clean)->toHaveCount(2);
    expect($clean[0])->toMatchArray(['name' => 'Participant', 'field' => 'Participant', 'type' => 'text', 'x' => 10]);
    expect($clean[1]['type'])->toBe('stamp');
});

it('rejects unknown zone types and malformed geometry', function () {
    expect(fn () => ZonePayloadValidator::validate([['name' => 'X', 'type' => 'hologram', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]]))
        ->toThrow(ValidationException::class);
    expect(fn () => ZonePayloadValidator::validate([['name' => 'X', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 0, 'h' => 10]]))
        ->toThrow(ValidationException::class);
    expect(fn () => ZonePayloadValidator::validate([['name' => 'X', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 10]]))
        ->toThrow(ValidationException::class);
    expect(fn () => ZonePayloadValidator::validate([['name' => 'Dup', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1], ['name' => 'Dup', 'type' => 'text', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1]]))
        ->toThrow(ValidationException::class);
});

it('matches a waiver when the name is similar and date/time/stamp align', function () {
    $result = app(WaiverValidationService::class)->validate(
        ['participant' => 'Juan Dela Cruz', 'date' => 'July 4, 2026', 'time' => '2:00 PM'],
        [
            'participant' => ['value' => 'Juan  dela cruz', 'type' => 'name'],
            'date' => ['value' => '2026-07-04', 'type' => 'date'],
            'time' => ['value' => '14:00', 'type' => 'time'],
        ],
        ['stamp' => true, 'signature' => true],
    );

    expect($result['valid'])->toBeTrue();
    expect($result['fields']['participant']['match'])->toBeTrue();
    expect($result['fields']['date']['match'])->toBeTrue();
});

it('fails when a required stamp is missing', function () {
    config(['waiver.require_stamp' => true]);

    $result = app(WaiverValidationService::class)->validate(
        ['name' => 'A'],
        ['name' => ['value' => 'A', 'type' => 'name']],
        ['stamp' => false, 'signature' => true],
    );

    expect($result['stamp_ok'])->toBeFalse();
    expect($result['valid'])->toBeFalse();
});

it('fails when the scanned date does not match the event', function () {
    $result = app(WaiverValidationService::class)->validate(
        ['date' => '2026-07-05'],
        ['date' => ['value' => '2026-07-04', 'type' => 'date']],
        ['stamp' => true, 'signature' => true],
    );

    expect($result['fields']['date']['match'])->toBeFalse();
    expect($result['valid'])->toBeFalse();
});

it('rejects a low-similarity name', function () {
    $result = app(WaiverValidationService::class)->validate(
        ['participant' => 'Totally Different Person'],
        ['participant' => ['value' => 'Juan Dela Cruz', 'type' => 'name']],
        ['stamp' => true, 'signature' => true],
    );

    expect($result['fields']['participant']['match'])->toBeFalse();
});
