<?php

namespace App\Forms;

use Illuminate\Support\Collection;

/**
 * Server-side recomputation of derived values: per-row totals on table-input
 * fields and `computed` fields (sums/differences over sibling keys or table
 * columns). Client-supplied values for these are never trusted — whatever was
 * posted is overwritten here before the payload is stored.
 */
final class FieldCompute
{
    /**
     * @param  Collection<int,\App\Models\Form\FormDescription>  $fields
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public static function apply(Collection $fields, array $payload): array
    {
        // Row totals first, so computed fields can aggregate them.
        foreach ($fields as $field) {
            if ($field->field_type !== FieldType::TABLE_INPUT) {
                continue;
            }
            $options = (array) ($field->field_options ?? []);
            $rowTotal = (array) ($options['row_total'] ?? []);
            $multiply = array_values(array_filter(array_map('strval', (array) ($rowTotal['multiply'] ?? []))));
            if (count($multiply) < 2) {
                continue;
            }

            $totalKey = (string) ($rowTotal['key'] ?? 'row_total');
            $rows = is_array($payload[$field->field_key] ?? null) ? $payload[$field->field_key] : [];
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $product = 1.0;
                foreach ($multiply as $columnKey) {
                    $product *= (float) ($row[$columnKey] ?? 0);
                }
                $rows[$i][$totalKey] = self::round($product);
            }
            $payload[$field->field_key] = $rows;
        }

        foreach ($fields as $field) {
            if ($field->field_type !== FieldType::COMPUTED) {
                continue;
            }
            $payload[$field->field_key] = self::evaluate((array) ($field->field_options ?? []), $payload);
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $options
     * @param  array<string,mixed>  $payload
     */
    private static function evaluate(array $options, array $payload): float|int
    {
        $formula = (string) ($options['formula'] ?? 'sum');
        $args = array_values(array_filter(array_map('strval', (array) ($options['args'] ?? []))));

        switch ($formula) {
            case 'difference':
                $value = self::resolveNumber($payload, $args[0] ?? '');
                foreach (array_slice($args, 1) as $key) {
                    $value -= self::resolveNumber($payload, $key);
                }

                return self::round($value);

            case 'table_sum':
                $rows = is_array($payload[(string) ($options['table'] ?? '')] ?? null)
                    ? $payload[(string) $options['table']]
                    : [];
                $column = (string) ($options['column'] ?? 'row_total');
                $sum = 0.0;
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $sum += (float) ($row[$column] ?? 0);
                    }
                }

                return self::round($sum);

            case 'sum':
            default:
                $sum = 0.0;
                foreach ($args as $key) {
                    $sum += self::resolveNumber($payload, $key);
                }

                return self::round($sum);
        }
    }

    /**
     * A referenced value: a plain payload key, or `table.column` summed over
     * that table's rows (lets `difference` subtract one table's total from
     * another's without an intermediate computed field).
     *
     * @param  array<string,mixed>  $payload
     */
    private static function resolveNumber(array $payload, string $key): float
    {
        if ($key === '') {
            return 0.0;
        }

        if (str_contains($key, '.')) {
            [$table, $column] = explode('.', $key, 2);
            $rows = is_array($payload[$table] ?? null) ? $payload[$table] : [];
            $sum = 0.0;
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $sum += (float) ($row[$column] ?? 0);
                }
            }

            return $sum;
        }

        return (float) ($payload[$key] ?? 0);
    }

    private static function round(float $value): float|int
    {
        $rounded = round($value, 2);

        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }
}
