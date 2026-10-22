<?php

use App\Services\Scoring\TriggerValidator;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

it('accepts a valid scoring trigger payload', function () {
    $trigger = [
        'when' => [
            'source' => 'form_submission',
            'form_id' => 12,
        ],
        'if' => [
            'op' => 'and',
            'children' => [
                [
                    'op' => '>=',
                    'left' => ['var' => 'field:members_attended'],
                    'right' => ['value' => 15],
                ],
                [
                    'op' => '=',
                    'left' => ['var' => 'field:related_to_organization'],
                    'right' => ['value' => 1],
                ],
            ],
        ],
        'then' => [
            'add' => [
                'kind' => 'const',
                'value' => 2,
            ],
        ],
    ];

    (new TriggerValidator())->validate($trigger);

    expect(true)->toBeTrue();
});

it('rejects triggers with malformed variables', function () {
    $trigger = [
        'when' => [
            'source' => 'event_plan',
        ],
        'if' => [
            'op' => '=',
            'left' => ['var' => 'badprefix:members_attended'],
            'right' => ['value' => 10],
        ],
        'then' => [
            'add' => [
                'kind' => 'const',
                'value' => 1,
            ],
        ],
    ];

    expect(fn () => (new TriggerValidator())->validate($trigger))
        ->toThrow(ValidationException::class, 'Each comparison must reference a variable.');
});

it('counts text list rows in scoring rule comparisons and tallies', function () {
    $engine = new \App\Services\Scoring\ScoringRuleEngine();
    $reflection = new ReflectionClass($engine);
    $passes = fn (array $condition, array $record) => $reflection->getMethod('passes')->invoke($engine, $condition, $record);
    $contribution = fn (array $add, array $record) => $reflection->getMethod('contribution')->invoke($engine, $add, $record);

    $record = ['values' => ['faculty_advisers' => ['John Doe', 'Jane Doe', '  ']], 'user_id' => 0];
    $compare = fn (string $op, int $value) => ['op' => $op, 'left' => ['var' => 'field:faculty_advisers'], 'right' => ['value' => $value]];

    expect($passes($compare('>=', 2), $record))->toBeTrue()
        ->and($passes($compare('>', 2), $record))->toBeFalse()
        ->and($passes($compare('<', 3), $record))->toBeTrue()
        ->and($contribution(['kind' => 'count_list', 'var' => 'field:faculty_advisers'], $record))->toBe(2)
        ->and($contribution(['kind' => 'floor_div', 'var' => 'field:faculty_advisers', 'divisor' => 2], $record))->toBe(1)
        ->and($passes($compare('>=', 1), ['values' => ['faculty_advisers' => []], 'user_id' => 0]))->toBeFalse();
});

it('caps a rule\'s semester tally at its "limited to" amount', function () {
    $engine = new \App\Services\Scoring\ScoringRuleEngine();
    $records = array_fill(0, 5, ['values' => [], 'user_id' => 0]);
    $trigger = fn (?int $max) => ['then' => ['add' => array_filter(['kind' => 'const', 'value' => 1, 'max' => $max], fn ($v) => $v !== null)]];

    expect($engine->tallyRecords($trigger(null), $records))->toBe(5)
        ->and($engine->tallyRecords($trigger(3), $records))->toBe(3)
        ->and($engine->tallyRecords($trigger(10), $records))->toBe(5);
});

it('validates the optional "limited to" amount', function () {
    $trigger = fn ($max) => [
        'when' => ['source' => 'event_plan'],
        'then' => ['add' => ['kind' => 'const', 'value' => 1, 'max' => $max]],
    ];

    (new TriggerValidator())->validate($trigger(4));
    (new TriggerValidator())->validate($trigger(null));

    expect(fn () => (new TriggerValidator())->validate($trigger(0)))
        ->toThrow(ValidationException::class, 'limited to')
        ->and(fn () => (new TriggerValidator())->validate($trigger(2.5)))
        ->toThrow(ValidationException::class, 'limited to');
});
