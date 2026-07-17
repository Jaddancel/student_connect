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
