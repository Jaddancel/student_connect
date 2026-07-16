<?php

use App\Forms\ConditionEvaluator;

function fieldsFixture(): array
{
    return [
        ['field_key' => 'kind', 'field_options' => []],
        ['field_key' => 'other_kind', 'field_options' => [
            'visible_when' => ['field' => 'kind', 'op' => 'equals', 'value' => 'other'],
        ]],
        // Conditioned on a conditional field — hidden controller cascades.
        ['field_key' => 'details', 'field_options' => [
            'visible_when' => ['field' => 'other_kind', 'op' => 'filled'],
        ]],
    ];
}

it('resolves visibility from submitted values', function () {
    $map = ConditionEvaluator::visibilityMap(fieldsFixture(), fn ($key) => [
        'kind' => 'other', 'other_kind' => 'Special', 'details' => 'x',
    ][$key] ?? null);

    expect($map)->toBe(['kind' => true, 'other_kind' => true, 'details' => true]);
});

it('hides the dependents of a failed condition, cascading', function () {
    $map = ConditionEvaluator::visibilityMap(fieldsFixture(), fn ($key) => [
        'kind' => 'standard', 'other_kind' => 'Special',
    ][$key] ?? null);

    // other_kind fails its condition; details is hidden because its
    // controller is hidden even though other_kind holds a value.
    expect($map)->toBe(['kind' => true, 'other_kind' => false, 'details' => false]);
});

it('treats dangling references and cycles as visible', function () {
    $dangling = [['field_key' => 'a', 'field_options' => [
        'visible_when' => ['field' => 'ghost', 'op' => 'equals', 'value' => 'x'],
    ]]];
    expect(ConditionEvaluator::visibilityMap($dangling, fn () => null))->toBe(['a' => true]);

    $cycle = [
        ['field_key' => 'a', 'field_options' => ['visible_when' => ['field' => 'b', 'op' => 'filled']]],
        ['field_key' => 'b', 'field_options' => ['visible_when' => ['field' => 'a', 'op' => 'filled']]],
    ];
    // The builder rejects cycles; the evaluator just must not loop forever.
    $map = ConditionEvaluator::visibilityMap($cycle, fn () => 'x');
    expect($map)->toHaveKeys(['a', 'b']);
});

it('evaluates every operator over scalars', function () {
    $passes = fn (string $op, ?string $value, string $expected = 'yes') => ConditionEvaluator::passes(
        ['op' => $op, 'value' => $expected],
        $value,
    );

    expect($passes('equals', 'yes'))->toBeTrue()
        ->and($passes('equals', 'no'))->toBeFalse()
        ->and($passes('not_equals', 'no'))->toBeTrue()
        ->and($passes('contains', 'oh YES indeed'))->toBeTrue()
        ->and($passes('contains', 'nope'))->toBeFalse()
        ->and($passes('filled', 'anything'))->toBeTrue()
        ->and($passes('filled', '  '))->toBeFalse()
        ->and($passes('empty', ''))->toBeTrue()
        ->and($passes('empty', 'x'))->toBeFalse();
});

it('evaluates operators over checkbox-group arrays', function () {
    $condition = ['op' => 'equals', 'value' => 'b'];

    expect(ConditionEvaluator::passes($condition, ['a', 'b']))->toBeTrue()
        ->and(ConditionEvaluator::passes($condition, ['a']))->toBeFalse()
        ->and(ConditionEvaluator::passes(['op' => 'filled'], []))->toBeFalse()
        ->and(ConditionEvaluator::passes(['op' => 'empty'], ['a']))->toBeFalse();
});

it('builds the client conditions map from field models', function () {
    $field = new \App\Models\Form\FormDescription([
        'field_key' => 'other_kind',
        'field_options' => ['visible_when' => ['field' => 'kind', 'op' => 'equals', 'value' => 'other']],
    ]);
    $plain = new \App\Models\Form\FormDescription(['field_key' => 'kind', 'field_options' => []]);

    expect(ConditionEvaluator::clientConditions([$field, $plain]))->toBe([
        'other_kind' => ['field' => 'kind', 'op' => 'equals', 'value' => 'other'],
    ]);
});
