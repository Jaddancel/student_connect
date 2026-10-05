<?php

namespace App\Reports;

use Illuminate\Validation\ValidationException;

/**
 * Validates and normalises a report definition against the {@see SchemaCatalog}.
 *
 * Shape (stored on report_templates.definition):
 *
 *   parameters: [{ name, label, type: entity|text|number|date, entity?, display?: [path…], required }]
 *   tokens:     [token…]
 *
 *   group token  { id, kind: 'group', name,
 *                  entity   (top level: a table)  | relation (nested: a has_many of the parent's table),
 *                  where: [cond…], order: [{ column, dir }], limit?, children: [token…] }
 *   value token  { id, kind: 'value', name, mode: 'field' | 'aggregate',
 *                  from     (top level only: the table to read),
 *                  path     (field mode: belongs_to relations… then a column, from the context table),
 *                  fn, relation?, column?   (aggregate mode: fn over the context's has_many `relation`
 *                                            — or, at top level, over `from` itself — of `column`),
 *                  where: [cond…], format: { type, pattern?, fallback? } }
 *   cond         { column, op, value? | param? }
 *
 * A group's children read from the group's table ("the entity selected by the
 * group token in each iteration"). Every identifier must exist in the catalog;
 * names are lowercase snake_case and unique among siblings.
 */
final class ReportDefinitionValidator
{
    public const OPS = ['=', '!=', '<', '<=', '>', '>=', 'contains', 'in', 'not_in', 'is_null', 'not_null'];

    public const AGGREGATES = ['count', 'sum', 'min', 'max', 'avg'];

    public const FORMATS = ['text', 'date', 'datetime', 'time', 'title', 'upper', 'lower', 'number'];

    public const PARAM_TYPES = ['entity', 'text', 'number', 'date'];

    private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** Names reserved for the universal/system token namespaces. */
    private const RESERVED = ['profile', 'system', 'param'];

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<string, array<string, mixed>> */
    private array $parameters = [];

    public function __construct(private readonly SchemaCatalog $catalog) {}

    /**
     * @param  array<string, mixed>  $definition
     * @return array{parameters: array<int, array<string,mixed>>, tokens: array<int, array<string,mixed>>}
     *
     * @throws ValidationException
     */
    public function validate(array $definition): array
    {
        $this->errors = [];
        $this->parameters = [];

        $parameters = $this->parameters((array) ($definition['parameters'] ?? []));
        $tokens = $this->tokens((array) ($definition['tokens'] ?? []), null, [], 1);

        if ($this->errors !== []) {
            throw ValidationException::withMessages(['definition' => array_values(array_unique($this->errors))]);
        }

        return ['parameters' => $parameters, 'tokens' => $tokens];
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function parameters(array $raw): array
    {
        $out = [];
        foreach (array_values($raw) as $i => $param) {
            if (! is_array($param)) {
                continue;
            }
            $name = trim((string) ($param['name'] ?? ''));
            $label = 'Parameter "'.($name !== '' ? $name : '#'.($i + 1)).'"';
            if (! preg_match(self::NAME_PATTERN, $name)) {
                $this->errors[] = $label.': names must be lowercase letters, digits and underscores, starting with a letter.';
            } elseif (isset($this->parameters[$name])) {
                $this->errors[] = $label.': another parameter already uses this name.';
            }

            $type = (string) ($param['type'] ?? 'text');
            if (! in_array($type, self::PARAM_TYPES, true)) {
                $this->errors[] = $label.': unknown type "'.$type.'".';
                $type = 'text';
            }

            $normalized = [
                'name' => $name,
                'label' => trim((string) ($param['label'] ?? '')) ?: \Illuminate\Support\Str::headline($name),
                'type' => $type,
                'required' => (bool) ($param['required'] ?? false),
            ];

            if ($type === 'entity') {
                $entity = (string) ($param['entity'] ?? '');
                if (! $this->catalog->has($entity)) {
                    $this->errors[] = $label.': unknown table "'.$entity.'".';
                } elseif ($this->catalog->primaryKey($entity) === null) {
                    $this->errors[] = $label.': table "'.$entity.'" has no single-column primary key to pick by.';
                } else {
                    $display = array_values(array_filter(array_map('strval', (array) ($param['display'] ?? []))));
                    if ($display !== []) {
                        $this->fieldPath($entity, $display, $label.' display');
                    }
                    $normalized['entity'] = $entity;
                    $normalized['display'] = $display;
                }
            }

            $this->parameters[$name] = $normalized;
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @param  array<int, string>  $trail
     * @return array<int, array<string, mixed>>
     */
    private function tokens(array $raw, ?string $context, array $trail, int $depth): array
    {
        $out = [];
        $names = [];

        foreach (array_values($raw) as $i => $token) {
            if (! is_array($token)) {
                continue;
            }
            $name = trim((string) ($token['name'] ?? ''));
            $path = array_merge($trail, [$name !== '' ? $name : '#'.($i + 1)]);
            $label = 'Token "'.implode(' › ', $path).'"';

            if (! preg_match(self::NAME_PATTERN, $name) || str_contains($name, '__')) {
                $this->errors[] = $label.': names must be lowercase letters, digits and single underscores, starting with a letter.';
            } elseif ($context === null && in_array($name, self::RESERVED, true)) {
                $this->errors[] = $label.': "'.$name.'" is reserved.';
            } elseif (isset($names[$name])) {
                $this->errors[] = $label.': another token at this level already uses this name.';
            }
            $names[$name] = true;

            $kind = (string) ($token['kind'] ?? 'value');
            $out[] = $kind === 'group'
                ? $this->group($token, $name, $context, $path, $label, $depth)
                : $this->value($token, $name, $context, $label);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $token
     * @param  array<int, string>  $path
     * @return array<string, mixed>
     */
    private function group(array $token, string $name, ?string $context, array $path, string $label, int $depth): array
    {
        if ($depth > (int) config('reports.max_depth', 3)) {
            $this->errors[] = $label.': groups can be nested at most '.(int) config('reports.max_depth', 3).' levels deep.';
        }

        $normalized = [
            'id' => (string) ($token['id'] ?? ''),
            'kind' => 'group',
            'name' => $name,
        ];

        $table = null;
        if ($context === null) {
            $entity = (string) ($token['entity'] ?? '');
            if (! $this->catalog->has($entity)) {
                $this->errors[] = $label.': choose a table to repeat FOR EVERY row of.';
            } else {
                $table = $entity;
            }
            $normalized['entity'] = $entity;
        } else {
            $relationName = (string) ($token['relation'] ?? '');
            $relation = $this->catalog->relation($context, $relationName);
            if ($relation === null || $relation['type'] !== 'has_many') {
                $this->errors[] = $label.': choose which related rows of "'.$context.'" to repeat for.';
            } else {
                $table = $relation['table'];
            }
            $normalized['relation'] = $relationName;
        }

        if ($table !== null && $this->catalog->primaryKey($table) === null) {
            $this->errors[] = $label.': table "'.$table.'" has no single-column primary key, so it cannot be repeated.';
        }

        $normalized['where'] = $table !== null ? $this->conditions((array) ($token['where'] ?? []), $table, $label) : [];
        $normalized['order'] = $table !== null ? $this->order((array) ($token['order'] ?? []), $table, $label) : [];
        $limit = $token['limit'] ?? null;
        $normalized['limit'] = is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
        $normalized['children'] = $table !== null
            ? $this->tokens((array) ($token['children'] ?? []), $table, $path, $depth + 1)
            : [];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function value(array $token, string $name, ?string $context, string $label): array
    {
        $mode = (string) ($token['mode'] ?? 'field');
        $normalized = [
            'id' => (string) ($token['id'] ?? ''),
            'kind' => 'value',
            'name' => $name,
            'mode' => in_array($mode, ['field', 'aggregate'], true) ? $mode : 'field',
            'format' => $this->format((array) ($token['format'] ?? []), $label),
        ];

        $table = $context;
        if ($context === null) {
            $from = (string) ($token['from'] ?? '');
            if (! $this->catalog->has($from)) {
                $this->errors[] = $label.': choose the table to read FROM.';
                $table = null;
            } else {
                $table = $from;
            }
            $normalized['from'] = $from;
        }

        if ($table === null) {
            $normalized += ['path' => [], 'where' => []];

            return $normalized;
        }

        if ($normalized['mode'] === 'field') {
            $path = array_values(array_map('strval', (array) ($token['path'] ?? [])));
            $this->fieldPath($table, $path, $label);
            $normalized['path'] = $path;
            $normalized['where'] = $context === null
                ? $this->conditions((array) ($token['where'] ?? []), $table, $label)
                : [];

            return $normalized;
        }

        // Aggregate: over the context's has_many relation, or (top level) over
        // the FROM table itself.
        $fn = (string) ($token['fn'] ?? 'count');
        if (! in_array($fn, self::AGGREGATES, true)) {
            $this->errors[] = $label.': unknown aggregate "'.$fn.'".';
            $fn = 'count';
        }
        $normalized['fn'] = $fn;

        $target = $table;
        if ($context !== null) {
            $relationName = (string) ($token['relation'] ?? '');
            $relation = $this->catalog->relation($context, $relationName);
            if ($relation === null || $relation['type'] !== 'has_many') {
                $this->errors[] = $label.': choose which related rows of "'.$context.'" to '.strtoupper($fn).'.';
                $target = null;
            } else {
                $target = $relation['table'];
            }
            $normalized['relation'] = $relationName;
        }

        $column = (string) ($token['column'] ?? '');
        if ($fn !== 'count' || $column !== '') {
            if ($target !== null && ! $this->catalog->hasColumn($target, $column)) {
                $this->errors[] = $label.': choose a column of "'.$target.'" to '.strtoupper($fn).'.';
            }
        }
        $normalized['column'] = $column !== '' ? $column : null;
        $normalized['where'] = $target !== null ? $this->conditions((array) ($token['where'] ?? []), $target, $label) : [];

        return $normalized;
    }

    /**
     * Validate a belongs_to… → column path from $table.
     *
     * @param  array<int, string>  $path
     */
    public function fieldPath(string $table, array $path, string $label): bool
    {
        if ($path === []) {
            $this->errors[] = $label.': choose a column to RETURN.';

            return false;
        }

        $current = $table;
        $column = array_pop($path);
        foreach ($path as $segment) {
            $relation = $this->catalog->relation($current, $segment);
            if ($relation === null || $relation['type'] !== 'belongs_to') {
                $this->errors[] = $label.': "'.$segment.'" is not a related record of "'.$current.'".';

                return false;
            }
            $current = $relation['table'];
        }

        if (! $this->catalog->hasColumn($current, $column)) {
            $this->errors[] = $label.': "'.$column.'" is not an available column of "'.$current.'".';

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function conditions(array $raw, string $table, string $label): array
    {
        $out = [];
        foreach ($raw as $cond) {
            if (! is_array($cond)) {
                continue;
            }
            $column = (string) ($cond['column'] ?? '');
            $op = (string) ($cond['op'] ?? '=');
            if (! $this->catalog->hasColumn($table, $column)) {
                $this->errors[] = $label.': WHERE uses an unknown column "'.$column.'" of "'.$table.'".';

                continue;
            }
            if (! in_array($op, self::OPS, true)) {
                $this->errors[] = $label.': unknown WHERE operator "'.$op.'".';

                continue;
            }

            $normalized = ['column' => $column, 'op' => $op];
            if (! in_array($op, ['is_null', 'not_null'], true)) {
                $param = trim((string) ($cond['param'] ?? ''));
                if ($param !== '') {
                    if (! isset($this->parameters[$param])) {
                        $this->errors[] = $label.': WHERE asks for an undefined parameter "'.$param.'".';
                    }
                    $normalized['param'] = $param;
                } else {
                    $value = $cond['value'] ?? '';
                    $normalized['value'] = is_array($value)
                        ? array_values(array_map('strval', $value))
                        : (string) $value;
                }
            }
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<int, array{column:string, dir:string}>
     */
    private function order(array $raw, string $table, string $label): array
    {
        $out = [];
        foreach ($raw as $order) {
            if (! is_array($order)) {
                continue;
            }
            $column = (string) ($order['column'] ?? '');
            if (! $this->catalog->hasColumn($table, $column)) {
                $this->errors[] = $label.': ORDER BY uses an unknown column "'.$column.'" of "'.$table.'".';

                continue;
            }
            $out[] = ['column' => $column, 'dir' => strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{type:string, pattern:?string, fallback:string}
     */
    private function format(array $raw, string $label): array
    {
        $type = (string) ($raw['type'] ?? 'text');
        if (! in_array($type, self::FORMATS, true)) {
            $this->errors[] = $label.': unknown format "'.$type.'".';
            $type = 'text';
        }

        $pattern = trim((string) ($raw['pattern'] ?? ''));

        return [
            'type' => $type,
            'pattern' => $pattern !== '' ? mb_substr($pattern, 0, 40) : null,
            'fallback' => mb_substr((string) ($raw['fallback'] ?? ''), 0, 120),
        ];
    }
}
