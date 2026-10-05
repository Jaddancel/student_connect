<?php

namespace App\Reports;

use App\Forms\FieldType;
use App\Support\UniversalField;
use Illuminate\Support\Str;

/**
 * Step-2 token-palette entries for a report definition, in the same shape
 * {@see \App\Http\Controllers\Admin\FormPrintTemplateController::tokensFor()}
 * emits for forms:
 *
 *  - top-level value tokens → `{{name}}`;
 *  - every group (at any depth, keyed by its dotted path P) → an
 *    `insert:'block'` entry wrapping `{{#P}} … {{/P}}` (repeats any content once
 *    per row) and, when it has values, an `insert:'table'` entry whose data row
 *    of `{{P.child#}}` repeats per row;
 *  - universal tokens: `{{profile.*}}` (the generating user / the organization
 *    picked in an organization parameter) and `{{system.*}}` (date, school
 *    year…).
 */
final class ReportPalette
{
    /**
     * @param  array{tokens: array<int, array<string,mixed>>}  $definition  a validated definition
     * @return array<int, array<string, mixed>>
     */
    public static function tokens(array $definition): array
    {
        $out = [];
        foreach ((array) ($definition['tokens'] ?? []) as $token) {
            if (($token['kind'] ?? 'value') === 'value') {
                $out[] = [
                    'key' => (string) $token['name'],
                    'label' => Str::headline((string) $token['name']),
                    'icon' => self::icon($token),
                    'group' => 'Report values',
                ];
            }
        }

        foreach ((array) ($definition['tokens'] ?? []) as $token) {
            if (($token['kind'] ?? 'value') === 'group') {
                self::group($token, (string) $token['name'], (string) ($token['entity'] ?? ''), $out);
            }
        }

        foreach (['profile' => 'Profile', 'org' => 'Organization'] as $source => $label) {
            foreach (UniversalField::keysBySource($source) as $key) {
                $meta = UniversalField::get($key);
                $out[] = [
                    'key' => 'profile.'.$key,
                    'label' => (string) ($meta['label'] ?? $key),
                    'icon' => (string) (FieldType::catalog()[$meta['type'] ?? FieldType::TEXT]['icon'] ?? 'text'),
                    'group' => $label,
                ];
            }
        }
        foreach (UniversalField::keysBySource('system') as $key) {
            $meta = UniversalField::get($key);
            $out[] = [
                'key' => 'system.'.$key,
                'label' => (string) ($meta['label'] ?? $key),
                'icon' => (string) (FieldType::catalog()[$meta['type'] ?? FieldType::TEXT]['icon'] ?? 'text'),
                'group' => 'System',
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $out
     */
    private static function group(array $token, string $path, string $over, array &$out): void
    {
        $values = [];
        foreach ((array) ($token['children'] ?? []) as $child) {
            if (($child['kind'] ?? 'value') === 'value') {
                $values[] = $child;
            }
        }

        $relation = (string) ($token['relation'] ?? '');
        $subject = Str::headline($relation !== '' ? $relation : $over);
        $known = ['#'.$path, '/'.$path];

        // One item per group; the palette offers a button for each way to place it.
        $out[] = [
            'key' => $path,
            'label' => 'For every '.$subject.' ('.$path.')',
            'icon' => 'table',
            'group' => 'Repeating groups',
            'actions' => $values === [] ? ['block'] : ['table', 'block'],
            'known' => $known,
            'children' => array_map(fn (array $child) => [
                'key' => $path.'.'.$child['name'],
                'insert_key' => $path.'.'.$child['name'],
                'label' => Str::headline((string) $child['name']),
                'icon' => self::icon($child),
                'type_label' => ($child['mode'] ?? 'field') === 'aggregate' ? strtoupper((string) ($child['fn'] ?? 'count')) : 'Value',
            ], $values),
        ];

        foreach ((array) ($token['children'] ?? []) as $child) {
            if (($child['kind'] ?? 'value') === 'group') {
                self::group($child, $path.'.'.$child['name'], (string) ($child['relation'] ?? ''), $out);
            }
        }
    }

    private static function icon(array $token): string
    {
        if (($token['mode'] ?? 'field') === 'aggregate') {
            return 'number';
        }

        return in_array((string) (($token['format'] ?? [])['type'] ?? 'text'), ['date', 'datetime', 'time'], true) ? 'date' : 'text';
    }
}
