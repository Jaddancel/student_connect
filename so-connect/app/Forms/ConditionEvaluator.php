<?php

namespace App\Forms;

/**
 * Evaluates per-field visibility conditions (`field_options.visible_when`) —
 * a field can be shown only when a condition on ANOTHER field of the same form
 * holds (e.g. a specific option is selected on a dropdown).
 *
 * Shape: {field: <controlling field_key>, op: <OPS>, value: <expected>}.
 * A hidden controller cascades: fields conditioned on a hidden field are
 * hidden too. Dangling references and (defensively) cycles resolve to visible
 * — the builder rejects both at save time.
 *
 * This is the server-side authority; resources/js/components/form-conditions.js
 * mirrors the same semantics for the live form.
 */
final class ConditionEvaluator
{
    public const OPS = ['equals', 'not_equals', 'contains', 'filled', 'empty'];

    /** Ops that compare against an expected value (the rest test presence). */
    public const VALUE_OPS = ['equals', 'not_equals', 'contains'];

    /**
     * Resolve every field's visibility against submitted/current values.
     *
     * @param  iterable<int, \App\Models\Form\FormDescription|array<string,mixed>>  $fields
     * @param  callable(string): mixed  $valueFor  current value of a field key
     * @return array<string,bool>  field_key => visible
     */
    public static function visibilityMap(iterable $fields, callable $valueFor): array
    {
        $conditions = [];
        $keys = [];

        foreach ($fields as $field) {
            $key = is_array($field) ? ($field['field_key'] ?? null) : $field->field_key;
            if (! $key) {
                continue;
            }
            $keys[] = $key;

            $options = is_array($field)
                ? (array) ($field['field_options'] ?? [])
                : (array) ($field->field_options ?? []);
            $condition = $options['visible_when'] ?? null;
            if (is_array($condition) && ($condition['field'] ?? '') !== '' && ($condition['op'] ?? '') !== '') {
                $conditions[$key] = $condition;
            }
        }

        $memo = [];
        $resolve = function (string $key, array $stack) use (&$resolve, &$memo, $conditions, $keys, $valueFor): bool {
            if (array_key_exists($key, $memo)) {
                return $memo[$key];
            }
            $condition = $conditions[$key] ?? null;
            if ($condition === null || in_array($key, $stack, true)) {
                return $memo[$key] = true;
            }

            $controller = (string) $condition['field'];
            if (! in_array($controller, $keys, true)) {
                return $memo[$key] = true; // dangling reference — fail open
            }

            if (! $resolve($controller, [...$stack, $key])) {
                return $memo[$key] = false; // hidden controller cascades
            }

            return $memo[$key] = self::passes($condition, $valueFor($controller));
        };

        $map = [];
        foreach ($keys as $key) {
            $map[$key] = $resolve($key, []);
        }

        return $map;
    }

    /**
     * Whether one condition holds for a value (string, array for checkbox
     * groups, or null).
     *
     * @param  array<string,mixed>  $condition
     */
    public static function passes(array $condition, mixed $value): bool
    {
        $op = (string) ($condition['op'] ?? 'equals');
        $expected = trim((string) ($condition['value'] ?? ''));

        $list = null;
        if (is_array($value)) {
            $list = array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), $value),
                fn (string $v) => $v !== '',
            ));
        }
        $scalar = $list === null ? trim((string) ($value ?? '')) : '';
        $filled = $list === null ? $scalar !== '' : $list !== [];

        return match ($op) {
            'equals' => $list === null ? $scalar === $expected : in_array($expected, $list, true),
            'not_equals' => $list === null ? $scalar !== $expected : ! in_array($expected, $list, true),
            'contains' => $list === null
                ? ($expected !== '' && str_contains(mb_strtolower($scalar), mb_strtolower($expected)))
                : in_array($expected, $list, true),
            'filled' => $filled,
            'empty' => ! $filled,
            default => true,
        };
    }

    /**
     * The conditions map a rendered form ships to the client:
     * field_key => {field, op, value}.
     *
     * @param  iterable<int, \App\Models\Form\FormDescription>  $fields
     * @return array<string, array{field:string, op:string, value:string}>
     */
    public static function clientConditions(iterable $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $condition = ((array) ($field->field_options ?? []))['visible_when'] ?? null;
            if (is_array($condition) && ($condition['field'] ?? '') !== '' && ($condition['op'] ?? '') !== '') {
                $map[$field->field_key] = [
                    'field' => (string) $condition['field'],
                    'op' => (string) $condition['op'],
                    'value' => (string) ($condition['value'] ?? ''),
                ];
            }
        }

        return $map;
    }
}
