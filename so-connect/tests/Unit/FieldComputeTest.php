<?php

use App\Forms\FieldCompute;
use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Forms\FieldKit;
use App\Models\Form\FormDescription;
use Illuminate\Support\Collection;

it('computes the organization member total from the three year levels', function () {
    $fields = new Collection([
        new FormDescription(['field_key' => 'freshman', 'field_type' => FieldType::NUMBER]),
        new FormDescription(['field_key' => 'sophomore', 'field_type' => FieldType::NUMBER]),
        new FormDescription(['field_key' => 'junior', 'field_type' => FieldType::NUMBER]),
        new FormDescription([
            'field_key' => 'total',
            'field_type' => FieldType::COMPUTED,
            'field_options' => ['formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior']],
        ]),
    ]);

    $payload = FieldCompute::apply($fields, [
        'freshman' => 11,
        'sophomore' => 12,
        'junior' => 13,
        'total' => 999,
    ]);

    expect($payload['total'])->toBe(36)
        ->and(FieldKit::types(SystemFunction::NEW_ORGANIZATION_REGISTRATION))->toContain(FieldType::COMPUTED);
});
function rowTotalTable(array $rowTotal): Collection
{
    return new Collection([
        new FormDescription([
            'field_key' => 'budget',
            'field_type' => FieldType::TABLE_INPUT,
            'field_options' => [
                'columns' => [
                    ['key' => 'a', 'label' => 'A', 'type' => 'number'],
                    ['key' => 'b', 'label' => 'B', 'type' => 'number'],
                    ['key' => 'c', 'label' => 'C', 'type' => 'number'],
                ],
                'row_total' => $rowTotal,
            ],
        ]),
        new FormDescription([
            'field_key' => 'grand',
            'field_type' => FieldType::COMPUTED,
            'field_options' => ['formula' => 'sum', 'args' => ['budget.line']],
        ]),
    ]);
}

it('applies the chosen row-total operation left to right, ignoring client values', function (string $op, array $expected, int|float $grand) {
    $payload = FieldCompute::apply(rowTotalTable(['key' => 'line', 'label' => 'Line', 'op' => $op, 'multiply' => ['a', 'b', 'c']]), [
        'budget' => [
            ['a' => '100', 'b' => '20', 'c' => '5', 'line' => '999'],
            ['a' => '9', 'b' => '0', 'c' => '3', 'line' => '999'],
        ],
    ]);

    expect(array_column($payload['budget'], 'line'))->toBe($expected)
        ->and($payload['grand'])->toBe($grand);
})->with([
    'multiply' => ['multiply', [10000, 0], 10000],
    'add' => ['add', [125, 12], 137],
    'subtract' => ['subtract', [75, 6], 81],
    // Divide by zero yields 0 instead of failing; 100 ÷ 20 ÷ 5 = 1.
    'divide' => ['divide', [1, 0], 1],
]);

it('keeps multiplying a row total saved before operations existed', function () {
    $payload = FieldCompute::apply(rowTotalTable(['key' => 'line', 'label' => 'Line', 'multiply' => ['a', 'b']]), [
        'budget' => [['a' => '2.5', 'b' => '4', 'c' => '']],
    ]);

    expect($payload['budget'][0]['line'])->toBe(10);
});

it('normalizes a row total and falls back to a default key and label', function () {
    expect(FieldType::tableRowTotal(['row_total' => ['key' => '', 'label' => '', 'op' => 'bogus', 'multiply' => ['a', ' ', 'b']]]))
        ->toBe(['key' => 'row_total', 'label' => 'Total', 'op' => 'multiply', 'columns' => ['a', 'b']])
        ->and(FieldType::tableRowTotal(['row_total' => ['key' => 'line', 'op' => 'add', 'multiply' => ['a']]]))->toBeNull()
        ->and(FieldType::tableRowTotal([]))->toBeNull()
        ->and(round(FieldType::applyRowTotal('divide', [10, 4]), 2))->toBe(2.5);
});

it('fills a number field with the total of a chosen table column', function () {
    $fields = new Collection([
        new FormDescription([
            'field_key' => 'funds',
            'field_type' => FieldType::TABLE_INPUT,
            'field_options' => [
                'columns' => [
                    ['key' => 'source', 'label' => 'Source', 'type' => 'text'],
                    ['key' => 'cash', 'label' => 'Cash', 'type' => 'number'],
                    ['key' => 'check', 'label' => 'Check', 'type' => 'number'],
                ],
                'row_total' => ['key' => 'line', 'op' => 'add', 'multiply' => ['cash', 'check']],
            ],
        ]),
        new FormDescription(['field_key' => 'cash_total', 'field_type' => FieldType::NUMBER,
            'field_options' => ['calculate_from' => 'funds', 'calculate_column' => 'cash']]),
        // The row total is computed first, so it can be totalled too.
        new FormDescription(['field_key' => 'grand_total', 'field_type' => FieldType::NUMBER,
            'field_options' => ['calculate_from' => 'funds', 'calculate_column' => 'line']]),
        // Text columns can't be totalled; the posted value is left alone.
        new FormDescription(['field_key' => 'bogus', 'field_type' => FieldType::NUMBER,
            'field_options' => ['calculate_from' => 'funds', 'calculate_column' => 'source']]),
        // A date source stays a client-side age; the server leaves it alone.
        new FormDescription(['field_key' => 'age', 'field_type' => FieldType::NUMBER,
            'field_options' => ['calculate_from' => 'birthday']]),
        new FormDescription(['field_key' => 'doubled', 'field_type' => FieldType::COMPUTED,
            'field_options' => ['formula' => 'sum', 'args' => ['cash_total', 'cash_total']]]),
    ]);

    $payload = FieldCompute::apply($fields, [
        'funds' => [
            ['source' => 'Dues', 'cash' => '100', 'check' => '50'],
            ['source' => 'Bake sale', 'cash' => '40.25', 'check' => ''],
        ],
        'cash_total' => '999',
        'grand_total' => '999',
        'bogus' => '7',
        'age' => '21',
    ]);

    expect($payload['cash_total'])->toBe(140.25)
        ->and($payload['grand_total'])->toBe(190.25)
        ->and($payload['bogus'])->toBe('7')
        ->and($payload['age'])->toBe('21')
        ->and($payload['doubled'])->toBe(280.5);

    expect(FieldCompute::apply($fields, ['funds' => []])['cash_total'])->toBe(0);
});
