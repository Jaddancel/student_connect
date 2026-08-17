<?php

use App\Forms\FieldType;

it('normalizes activity-table columns: drops bad keys, coerces types, dedupes, defaults labels', function () {
    $columns = FieldType::activityTableColumns(['columns' => [
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        // Invalid key (space) → dropped.
        ['key' => 'bad key', 'label' => 'Bad', 'type' => 'text'],
        // Empty key → dropped.
        ['key' => '', 'label' => 'Empty', 'type' => 'text'],
        // Unknown type → coerced to text.
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'not_a_type'],
        // Missing label → defaults to the key.
        ['key' => 'venue'],
        // Duplicate key → dropped (first wins).
        ['key' => 'title', 'label' => 'Dup', 'type' => 'date'],
        // Non-array entry → skipped.
        'garbage',
    ]]);

    expect($columns)->toBe([
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'text'],
        ['key' => 'venue', 'label' => 'venue', 'type' => 'text'],
    ]);
});

it('returns an empty list when there are no columns', function () {
    expect(FieldType::activityTableColumns([]))->toBe([]);
});
