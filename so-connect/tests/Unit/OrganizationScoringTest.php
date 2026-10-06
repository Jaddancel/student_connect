<?php

use App\Services\Scoring\TriggerValidator;
use Illuminate\Validation\ValidationException;

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
