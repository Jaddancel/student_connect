<?php

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a report definition into a data tree:
 *
 *   [ value_token => 'text', group_token => [ [child => …, nested_group => [ … ]], … ] ]
 *
 * Every identifier is re-validated against the {@see SchemaCatalog} before it
 * is compiled (so table/column names only ever come from the introspected
 * schema) and every value is a bound parameter. Groups are loaded in batches
 * per level — one query per group/aggregate token, never one per row — and
 * capped by `reports.max_rows` (`reports.preview_rows` in preview mode).
 */
final class ReportQueryEngine
{
    /** @var array<string, mixed> */
    private array $params = [];

    private bool $preview = false;

    private bool $truncated = false;

    public function __construct(
        private readonly SchemaCatalog $catalog,
        private readonly ReportDefinitionValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $parameterValues  name => raw input
     * @return array{data: array<string, mixed>, params: array<string, mixed>, truncated: bool}
     *
     * @throws ValidationException
     */
    public function run(array $definition, array $parameterValues = [], bool $preview = false): array
    {
        $definition = $this->validator->validate($definition);
        $this->preview = $preview;
        $this->truncated = false;
        $this->params = $this->resolveParameters($definition['parameters'], $parameterValues, $preview);
        if ($this->usesActiveOrganization($definition)) {
            $active = $this->activeOrganization();
            if ($active === null && ! $preview) {
                throw ValidationException::withMessages(['parameters' => ['Select an organization in the organization switcher first.']]);
            }
            $this->params[ReportDefinitionValidator::SESSION_ORGANIZATION] = $active['id'] ?? null;
        }

        $data = [];
        $raws = [];
        foreach ($definition['tokens'] as $token) {
            if ($token['kind'] === 'group') {
                $data[$token['name']] = $this->topGroup($token);
            } elseif ($token['mode'] !== 'compute') {
                [$data[$token['name']], $raws[$token['name']]] = $this->topValue($token);
            }
        }
        $data += $this->computeTokens($definition['tokens'], $raws);

        return ['data' => $data, 'params' => $this->params, 'truncated' => $this->truncated];
    }

    /**
     * Pick-list for an entity parameter: [{ value, label }].
     *
     * @param  array<string, mixed>  $parameter  a normalized parameter
     * @return array<int, array{value:string, label:string}>
     */
    public function parameterOptions(array $parameter): array
    {
        $table = (string) ($parameter['entity'] ?? '');
        $primary = $this->catalog->primaryKey($table);
        if ($primary === null) {
            return [];
        }

        $keys = $this->catalog->query($table)->orderBy($primary)->limit(1000)->pluck($primary)->all();
        $display = (array) ($parameter['display'] ?? []);
        $labels = $display !== [] ? $this->resolvePath($table, $keys, $display) : [];

        return array_map(fn ($key) => [
            'value' => (string) $key,
            'label' => trim((string) ($labels[(string) $key] ?? '')) !== ''
                ? (string) $labels[(string) $key]
                : Str::headline(Str::singular($table)).' #'.$key,
        ], $keys);
    }

    // ── Top level ──────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function topGroup(array $token): array
    {
        $table = $token['entity'];
        $primary = (string) $this->catalog->primaryKey($table);

        $query = $this->catalog->query($table)->select($this->selectColumns($table, $token['children']));
        $this->applyConditions($query, $token['where']);
        $this->applyOrder($query, $token['order'], $primary);
        $cap = $this->cap($token['limit']);
        $rows = $query->limit($cap + 1)->get();
        // Reaching an explicit LIMIT is intended, not truncation.
        $rows = $this->trim($rows, $cap, $token['limit'] === null || $cap < $token['limit']);

        return $this->buildItems($table, $rows, $token['children']);
    }

    /** @return array{0: string, 1: mixed} the formatted text and the raw value */
    private function topValue(array $token): array
    {
        $table = $token['from'];

        if ($token['mode'] === 'aggregate') {
            $query = $this->catalog->query($table);
            $this->applyConditions($query, $token['where']);

            $raw = $this->aggregate($query, $token['fn'], $token['column']);

            return [$this->format($raw, null, $token['column'], $token['format'], true), $raw];
        }

        $primary = $this->catalog->primaryKey($table);
        $query = $this->catalog->query($table);
        $this->applyConditions($query, $token['where']);

        if ($primary === null || count($token['path']) === 1) {
            if ($primary !== null) {
                $query->orderBy($primary);
            }
            $raw = $query->value($token['path'][0]);

            return [$this->format($raw, $table, $token['path'][0], $token['format']), $raw];
        }

        $key = $query->orderBy($primary)->value($primary);
        if ($key === null) {
            return [$this->format(null, null, null, $token['format']), null];
        }
        [$finalTable, $finalColumn] = $this->pathTarget($table, $token['path']);
        $raw = $this->resolvePath($table, [$key], $token['path'])[(string) $key] ?? null;

        return [$this->format($raw, $finalTable, $finalColumn, $token['format']), $raw];
    }

    // ── Groups ─────────────────────────────────────────────────────────────

    /**
     * Build one item per row (in order) with every child token resolved.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(string $table, Collection $rows, array $children): array
    {
        $primary = (string) $this->catalog->primaryKey($table);
        $keys = $rows->pluck($primary)->map(fn ($k) => (string) $k)->all();
        $items = array_fill(0, $rows->count(), []);
        // Raw (unformatted) value tokens per row, for compute tokens to read.
        $raws = array_fill(0, $rows->count(), []);

        foreach ($children as $child) {
            if ($child['kind'] === 'group') {
                $nested = $this->nestedGroup($table, $rows, $child);
                foreach ($rows->values() as $i => $row) {
                    $items[$i][$child['name']] = $nested[(string) ($row->{$this->catalog->relation($table, $child['relation'])['local']} ?? '')] ?? [];
                }

                continue;
            }

            if ($child['mode'] === 'compute') {
                continue;
            }

            if ($child['mode'] === 'aggregate') {
                $relation = $this->catalog->relation($table, $child['relation']);
                $parentValues = $rows->pluck($relation['local'])->filter(fn ($v) => $v !== null)->unique()->values()->all();
                $map = $this->groupedAggregate($relation, $parentValues, $child);
                foreach ($rows->values() as $i => $row) {
                    $raw = $map[(string) ($row->{$relation['local']} ?? '')] ?? ($child['fn'] === 'count' ? 0 : null);
                    $items[$i][$child['name']] = $this->format($raw, null, $child['column'], $child['format'], true);
                    $raws[$i][$child['name']] = $raw;
                }

                continue;
            }

            [$finalTable, $finalColumn] = $this->pathTarget($table, $child['path']);
            $values = $this->resolvePath($table, $keys, $child['path']);
            foreach ($keys as $i => $key) {
                $items[$i][$child['name']] = $this->format($values[$key] ?? null, $finalTable, $finalColumn, $child['format']);
                $raws[$i][$child['name']] = $values[$key] ?? null;
            }
        }

        foreach ($items as $i => $item) {
            $items[$i] += $this->computeTokens($children, $raws[$i]);
        }

        return $items;
    }

    /**
     * Items of a nested group for all parent rows at once, keyed by the
     * parent's referenced value.
     *
     * @param  Collection<int, object>  $parentRows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function nestedGroup(string $parentTable, Collection $parentRows, array $token): array
    {
        $relation = $this->catalog->relation($parentTable, $token['relation']);
        $table = $relation['table'];
        $primary = (string) $this->catalog->primaryKey($table);
        $parentValues = $parentRows->pluck($relation['local'])->filter(fn ($v) => $v !== null)->unique()->values()->all();
        if ($parentValues === []) {
            return [];
        }

        $columns = $this->selectColumns($table, $token['children']);
        if (! in_array($relation['foreign'], $columns, true)) {
            $columns[] = $relation['foreign'];
        }

        $rows = collect();
        foreach (array_chunk($parentValues, 1000) as $chunk) {
            $query = $this->catalog->query($table)->select($columns)->whereIn($relation['foreign'], $chunk);
            $this->applyConditions($query, $token['where']);
            $this->applyOrder($query, $token['order'], $primary);
            $rows = $rows->merge($query->limit((int) config('reports.max_rows', 5000) + 1)->get());
        }
        $rows = $this->trim($rows, (int) config('reports.max_rows', 5000));

        // Per-parent limit (explicit, or the preview cap).
        $perParent = $this->cap($token['limit']);
        $byParent = [];
        $kept = collect();
        foreach ($rows as $row) {
            $parent = (string) $row->{$relation['foreign']};
            if (count($byParent[$parent] ?? []) >= $perParent) {
                $this->truncated = $this->truncated || $this->preview || $token['limit'] === null;

                continue;
            }
            $byParent[$parent][] = true;
            $kept->push($row);
        }

        $items = $this->buildItems($table, $kept, $token['children']);
        $grouped = [];
        foreach ($kept->values() as $i => $row) {
            $grouped[(string) $row->{$relation['foreign']}][] = $items[$i];
        }

        return $grouped;
    }

    /**
     * @param  array{table:string, local:string, foreign:string}  $relation
     * @param  array<int, mixed>  $parentValues
     * @return array<string, mixed>
     */
    private function groupedAggregate(array $relation, array $parentValues, array $token): array
    {
        $map = [];
        foreach (array_chunk($parentValues, 1000) as $chunk) {
            $query = $this->catalog->query($relation['table'])->whereIn($relation['foreign'], $chunk);
            $this->applyConditions($query, $token['where']);
            $grammar = $query->getGrammar();
            $expression = $token['fn'] === 'count' && $token['column'] === null
                ? 'COUNT(*)'
                : strtoupper($token['fn']).'('.$grammar->wrap($token['column']).')';

            $rows = $query->groupBy($relation['foreign'])
                ->select([$relation['foreign'].' as __k', DB::raw($expression.' as __v')])
                ->get();
            foreach ($rows as $row) {
                $map[(string) $row->__k] = $row->__v;
            }
        }

        return $map;
    }

    private function aggregate(Builder $query, string $fn, ?string $column): mixed
    {
        return match ($fn) {
            'count' => $column === null ? $query->count() : $query->count($column),
            'sum' => $query->sum($column),
            'min' => $query->min($column),
            'max' => $query->max($column),
            'avg' => $query->avg($column),
        };
    }

    // ── Paths ──────────────────────────────────────────────────────────────

    /**
     * Resolve a belongs_to… → column path for a set of base-table primary keys.
     *
     * @param  array<int, mixed>  $keys
     * @param  array<int, string>  $path
     * @return array<string, mixed>  primary key => raw value
     */
    private function resolvePath(string $table, array $keys, array $path): array
    {
        $primary = $this->catalog->primaryKey($table);
        if ($primary === null || $keys === []) {
            return [];
        }

        $column = $path[count($path) - 1];
        $relations = array_slice($path, 0, -1);

        $out = [];
        foreach (array_chunk(array_values(array_unique($keys)), 1000) as $chunk) {
            $query = $this->catalog->query($table, 't0')->whereIn('t0.'.$primary, $chunk);
            $alias = 't0';
            $current = $table;
            foreach ($relations as $i => $segment) {
                $relation = $this->catalog->relation($current, $segment);
                $next = 't'.($i + 1);
                $query->leftJoin($relation['table'].' as '.$next, $next.'.'.$relation['foreign'], '=', $alias.'.'.$relation['local']);
                $alias = $next;
                $current = $relation['table'];
            }

            foreach ($query->get(['t0.'.$primary.' as __k', $alias.'.'.$column.' as __v']) as $row) {
                $out[(string) $row->__k] = $row->__v;
            }
        }

        return $out;
    }

    /**
     * The table and column a field path ends on.
     *
     * @param  array<int, string>  $path
     * @return array{0:string, 1:string}
     */
    private function pathTarget(string $table, array $path): array
    {
        $current = $table;
        foreach (array_slice($path, 0, -1) as $segment) {
            $current = $this->catalog->relation($current, $segment)['table'];
        }

        return [$current, $path[count($path) - 1]];
    }

    /**
     * Columns a group's rows must carry: the primary key plus every parent-side
     * column its nested groups/aggregates join on.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, string>
     */
    private function selectColumns(string $table, array $children): array
    {
        $columns = [(string) $this->catalog->primaryKey($table)];
        foreach ($children as $child) {
            if ($child['kind'] === 'group' || ($child['mode'] ?? null) === 'aggregate') {
                $columns[] = $this->catalog->relation($table, $child['relation'])['local'];
            }
        }

        return array_values(array_unique($columns));
    }

    // ── Conditions ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    private function applyConditions(Builder $query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $column = $condition['column'];
            $op = $condition['op'];

            if ($op === 'is_null') {
                $query->whereNull($column);

                continue;
            }
            if ($op === 'not_null') {
                $query->whereNotNull($column);

                continue;
            }

            if (isset($condition['param'])) {
                $value = $this->params[$condition['param']] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
            } else {
                $value = $condition['value'] ?? '';
            }

            match ($op) {
                'contains' => $query->where($column, 'like', '%'.addcslashes((string) $this->scalar($value), '%_\\').'%'),
                'in' => $query->whereIn($column, $this->list($value)),
                'not_in' => $query->whereNotIn($column, $this->list($value)),
                '=', '!=', '<', '<=', '>', '>=' => $query->where($column, $op === '!=' ? '<>' : $op, $this->scalar($value)),
            };
        }
    }

    private function applyOrder(Builder $query, array $order, string $primary): void
    {
        foreach ($order as $clause) {
            $query->orderBy($clause['column'], $clause['dir']);
        }
        $query->orderBy($primary);
    }

    /** @return array<int, string> */
    private function list(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    private function scalar(mixed $value): string
    {
        return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
    }

    // ── Parameters ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function resolveParameters(array $parameters, array $values, bool $preview): array
    {
        $resolved = [];
        $errors = [];
        foreach ($parameters as $parameter) {
            if (($parameter['context'] ?? null) === ReportDefinitionValidator::CONTEXT_ACTIVE_ORGANIZATION) {
                $active = $this->activeOrganization();
                if ($active === null && ! $preview) {
                    $errors[] = 'Select an organization in the organization switcher first.';
                }
                $resolved[$parameter['name']] = $active['id'] ?? null;

                continue;
            }

            $raw = $values[$parameter['name']] ?? null;
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === null || $raw === '') {
                if ($parameter['required'] && ! $preview) {
                    $errors[] = $parameter['label'].' is required.';
                }
                $resolved[$parameter['name']] = null;

                continue;
            }

            $resolved[$parameter['name']] = match ($parameter['type']) {
                'number', 'entity' => is_numeric($raw) ? $raw + 0 : (string) $raw,
                'date' => $this->dateOrNull((string) $raw),
                default => (string) $raw,
            };
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['parameters' => $errors]);
        }

        return $resolved;
    }

    /**
     * Whether any WHERE condition of a (validated) definition filters on the
     * organization selected in the session.
     *
     * @param  array{parameters: array<int, array<string,mixed>>, tokens: array<int, array<string,mixed>>}  $definition
     */
    public function usesActiveOrganization(array $definition): bool
    {
        $walk = function (array $tokens) use (&$walk): bool {
            foreach ($tokens as $token) {
                foreach ((array) ($token['where'] ?? []) as $condition) {
                    if (($condition['param'] ?? null) === ReportDefinitionValidator::SESSION_ORGANIZATION) {
                        return true;
                    }
                }
                if (($token['kind'] ?? null) === 'group' && $walk((array) ($token['children'] ?? []))) {
                    return true;
                }
            }

            return false;
        };

        return $walk($definition['tokens']);
    }

    /**
     * The organization the signed-in user has selected for this session
     * (`active_organization_id`, set by the organization switcher). Non-admins
     * must belong to it.
     *
     * @return array{id: int, name: string}|null
     */
    public function activeOrganization(): ?array
    {
        $id = (int) session('active_organization_id', 0);
        $user = Auth::user();
        if ($id <= 0 || $user === null) {
            return null;
        }

        if ((int) $user->user_type !== 2
            && ! DB::table('organization_officers')->where('user', (int) $user->getKey())->where('organization', $id)->exists()) {
            return null;
        }

        $organization = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $id)
            ->first(['o.organization_id', 'od.name']);

        return $organization === null ? null : ['id' => $id, 'name' => (string) ($organization->name ?? 'Unknown Organization')];
    }

    private function dateOrNull(string $raw): ?string
    {
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Formatting ─────────────────────────────────────────────────────────

    /**
     * @param  array{type:string, pattern:?string, fallback:string}  $format
     */
    private function format(mixed $raw, ?string $table, ?string $column, array $format, bool $numeric = false): string
    {
        if ($table !== null && $column !== null) {
            $label = $this->catalog->enumLabel($table, $column, $raw);
            if ($label !== null) {
                $raw = $label;
            } elseif (($this->catalog->column($table, $column)['type'] ?? null) === 'boolean' && $raw !== null) {
                $raw = (bool) $raw ? 'Yes' : 'No';
            }
        }

        if (is_string($raw) && $raw !== '' && in_array($raw[0], ['[', '{'], true)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        if (is_array($raw)) {
            $raw = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $raw));
        }

        $text = $raw === null ? '' : (string) $raw;
        $pattern = $format['pattern'] ?? null;

        if ($text !== '') {
            try {
                $text = match ($format['type']) {
                    'date' => Carbon::parse($text)->format($pattern ?: 'F j, Y'),
                    'datetime' => Carbon::parse($text)->format($pattern ?: 'F j, Y g:i A'),
                    'time' => Carbon::parse($text)->format($pattern ?: 'g:i A'),
                    'title' => Str::title($text),
                    'upper' => Str::upper($text),
                    'lower' => Str::lower($text),
                    'number' => is_numeric($text) ? number_format((float) $text, max(0, min(6, (int) ($pattern ?? 0)))) : $text,
                    default => ($numeric && is_numeric($text) && str_contains($text, '.'))
                        ? rtrim(rtrim(number_format((float) $text, 2, '.', ''), '0'), '.')
                        : $text,
                };
            } catch (\Throwable) {
                // Unparseable dates print as stored.
            }
        }

        return $text !== '' ? $text : (string) ($format['fallback'] ?? '');
    }

    // ── Compute tokens ─────────────────────────────────────────────────────

    /** @var array<string, array<int, mixed>> */
    private array $expressions = [];

    /**
     * Evaluate the compute tokens among $children against the raw values of
     * their sibling value tokens. Computes may read other computes, so they run
     * in dependency order (the validator guarantees there is no loop).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, mixed>  $raws  sibling value token name => raw value
     * @return array<string, string> compute token name => formatted text
     */
    private function computeTokens(array $children, array $raws): array
    {
        $pending = [];
        foreach ($children as $child) {
            if ($child['kind'] === 'value' && $child['mode'] === 'compute') {
                $this->expressions[$child['expression']] ??= ReportExpression::parse($child['expression']);
                $pending[$child['name']] = $child;
            }
        }
        if ($pending === []) {
            return [];
        }

        // Null reads as 0; anything else non-numeric leaves the result blank.
        $numbers = [];
        foreach ($raws as $name => $raw) {
            if ($raw === null) {
                $numbers[$name] = 0.0;
            } elseif (is_bool($raw) || is_numeric($raw)) {
                $numbers[$name] = (float) $raw;
            }
        }

        $out = [];
        while ($pending !== []) {
            $progressed = false;
            foreach ($pending as $name => $child) {
                $ast = $this->expressions[$child['expression']];
                if (array_intersect(ReportExpression::references($ast), array_keys($pending)) !== []) {
                    continue;
                }

                $result = ReportExpression::evaluate($ast, $numbers);
                $text = null;
                if ($result !== null && is_finite($result)) {
                    $result = round($result, 10);
                    $text = rtrim(rtrim(sprintf('%.10F', $result), '0'), '.');
                    $text = $text === '-0' ? '0' : $text;
                    $numbers[$name] = $result;
                }
                $out[$name] = $this->format($text, null, null, $child['format'], true);
                unset($pending[$name]);
                $progressed = true;
            }
            if (! $progressed) {
                break;
            }
        }

        return $out;
    }

    // ── Caps ───────────────────────────────────────────────────────────────

    private function cap(?int $limit): int
    {
        $cap = $this->preview ? (int) config('reports.preview_rows', 10) : (int) config('reports.max_rows', 5000);

        return $limit !== null ? min($limit, $cap) : $cap;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function trim(Collection $rows, int $cap, bool $flag = true): Collection
    {
        if ($rows->count() > $cap) {
            $this->truncated = $this->truncated || $flag;

            return $rows->take($cap)->values();
        }

        return $rows->values();
    }
}
