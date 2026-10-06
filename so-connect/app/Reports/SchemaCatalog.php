<?php

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The data catalog report tokens are authored and validated against, built by
 * live schema introspection of the app database:
 *
 *  - every table (minus `reports.deny_tables`), each with its columns (minus
 *    any matching `reports.deny_column_patterns`, e.g. passwords and tokens);
 *  - `belongs_to` relations from foreign keys (plus `reports.relations` for
 *    FK-less columns), named after the local column (`organization_id` →
 *    `organization`), and their reverse `has_many` relations, named after
 *    the child table (`organization_officers`, or
 *    `{table}_by_{column}` when a table references the parent twice).
 *
 * Identifiers in a report definition are only ever compiled into SQL after
 * they have been looked up here, so the catalog doubles as the whitelist.
 */
final class SchemaCatalog
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $tables = null;

    private FormTableSources $formTables;

    public function __construct(?FormTableSources $formTables = null)
    {
        $this->formTables = $formTables ?? new FormTableSources;
    }

    /**
     * @return array<string, array{name:string, label:string, primary:?string, columns:array<string, array{name:string,label:string,type:string,enum:bool}>, relations:array<string, array{name:string,label:string,type:string,table:string,local:string,foreign:string}>}>
     */
    public function tables(): array
    {
        if ($this->tables !== null) {
            return $this->tables;
        }

        // Form-table sources follow the live form definitions, so they are
        // merged in after the (cached) schema introspection.
        return $this->tables = $this->withFormTables(
            Cache::remember($this->cacheKey(), now()->addHours(12), fn () => $this->introspect()),
        );
    }

    /**
     * A query builder over a table, aliased when asked. Form-table sources
     * are derived tables (see {@see FormTableSources}).
     */
    public function query(string $table, ?string $alias = null): Builder
    {
        $sql = $this->formTables->sql($table);
        if ($sql === null) {
            return DB::table($alias !== null ? $table.' as '.$alias : $table);
        }

        return DB::query()->fromRaw('('.$sql[0].') as `'.str_replace('`', '', $alias ?? $table).'`', $sql[1]);
    }

    public function isVirtual(string $table): bool
    {
        return $this->formTables->has($table);
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
        $this->tables = null;
    }

    public function has(string $table): bool
    {
        return isset($this->tables()[$table]);
    }

    /** @return array<string, mixed>|null */
    public function table(string $table): ?array
    {
        return $this->tables()[$table] ?? null;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return isset($this->tables()[$table]['columns'][$column]);
    }

    /** @return array<string, mixed>|null */
    public function column(string $table, string $column): ?array
    {
        return $this->tables()[$table]['columns'][$column] ?? null;
    }

    /** @return array{name:string,label:string,type:string,table:string,local:string,foreign:string}|null */
    public function relation(string $table, string $name): ?array
    {
        return $this->tables()[$table]['relations'][$name] ?? null;
    }

    public function primaryKey(string $table): ?string
    {
        return $this->tables()[$table]['primary'] ?? null;
    }

    /**
     * Label for a stored enum code, when the column has a mapping.
     */
    public function enumLabel(string $table, string $column, mixed $value): ?string
    {
        $mapping = ((array) config('reports.enums', []))[$table.'.'.$column] ?? null;
        if (! is_array($mapping) || count($mapping) !== 2 || $value === null || $value === '') {
            return null;
        }

        [$class, $method] = $mapping;
        try {
            return (string) $class::$method(is_numeric($value) ? (int) $value : $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A compact JSON-friendly shape for the editor's dropdown pills.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEditor(): array
    {
        return array_values(array_map(fn (array $table) => [
            'name' => $table['name'],
            'label' => $table['label'],
            'primary' => $table['primary'],
            'columns' => array_values($table['columns']),
            'relations' => array_values($table['relations']),
        ], $this->tables()));
    }

    /**
     * Add the form-table sources and their relations to the organization and
     * the submission they come from.
     *
     * @param  array<string, array<string, mixed>>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function withFormTables(array $tables): array
    {
        $this->formTables = new FormTableSources;

        foreach ($this->formTables->tables() as $name => $table) {
            if (isset($tables[$name])) {
                continue;
            }

            foreach ([
                ['organization', 'organizations', 'organization_id', 'organization_id'],
                ['submission', 'form_submissions', 'submission_id', 'form_submission_id'],
            ] as [$relation, $parent, $local, $foreign]) {
                if (! isset($tables[$parent]['columns'][$foreign])) {
                    continue;
                }
                $table['relations'][$relation] = [
                    'name' => $relation,
                    'label' => Str::headline($relation).' ('.Str::headline($parent).')',
                    'type' => 'belongs_to',
                    'table' => $parent,
                    'local' => $local,
                    'foreign' => $foreign,
                ];
                $tables[$parent]['relations'][$name] = [
                    'name' => $name,
                    'label' => $table['label'],
                    'type' => 'has_many',
                    'table' => $name,
                    'local' => $foreign,
                    'foreign' => $local,
                ];
                ksort($tables[$parent]['relations']);
            }

            ksort($table['relations']);
            $tables[$name] = $table;
        }

        ksort($tables);

        return $tables;
    }

    private function cacheKey(): string
    {
        // Any migration changes the schema; tie the cache to the migration set.
        $fingerprint = '0';
        try {
            $fingerprint = (string) DB::table('migrations')->count().'-'.(string) DB::table('migrations')->max('id');
        } catch (\Throwable) {
        }

        return 'reports.schema.'.md5(DB::connection()->getDatabaseName().'|'.$fingerprint.'|'.json_encode([
            config('reports.deny_tables'),
            config('reports.deny_column_patterns'),
            config('reports.relations'),
        ]));
    }

    /** @return array<string, array<string, mixed>> */
    private function introspect(): array
    {
        $database = DB::connection()->getDatabaseName();
        $denyTables = array_map('strtolower', (array) config('reports.deny_tables', []));

        $names = [];
        foreach (Schema::getTables() as $table) {
            $schema = $table['schema'] ?? null;
            if ($schema !== null && $schema !== $database) {
                continue;
            }
            $name = (string) $table['name'];
            if (in_array(strtolower($name), $denyTables, true)) {
                continue;
            }
            $names[$name] = true;
        }
        ksort($names);

        $tables = [];
        foreach (array_keys($names) as $name) {
            $columns = [];
            foreach (Schema::getColumns($name) as $column) {
                $columnName = (string) $column['name'];
                if ($this->deniedColumn($columnName)) {
                    continue;
                }
                $columns[$columnName] = [
                    'name' => $columnName,
                    'label' => Str::headline($columnName),
                    'type' => $this->columnType((string) ($column['type_name'] ?? $column['type'] ?? '')),
                    'enum' => isset(((array) config('reports.enums', []))[$name.'.'.$columnName]),
                ];
            }

            $primary = null;
            foreach (Schema::getIndexes($name) as $index) {
                if (($index['primary'] ?? false) && count($index['columns']) === 1) {
                    $primary = (string) $index['columns'][0];
                }
            }

            $tables[$name] = [
                'name' => $name,
                'label' => Str::headline($name),
                'primary' => $primary !== null && isset($columns[$primary]) ? $primary : null,
                'columns' => $columns,
                'relations' => [],
            ];
        }

        // Collect belongs-to edges: real FKs first, then configured ones.
        $edges = [];
        foreach (array_keys($tables) as $name) {
            foreach (Schema::getForeignKeys($name) as $fk) {
                if (count($fk['columns']) !== 1 || count($fk['foreign_columns']) !== 1) {
                    continue;
                }
                $edges[] = [$name, (string) $fk['columns'][0], (string) $fk['foreign_table'], (string) $fk['foreign_columns'][0]];
            }
        }
        foreach ((array) config('reports.relations', []) as $edge) {
            if (is_array($edge) && count($edge) === 4) {
                $edges[] = array_map('strval', array_values($edge));
            }
        }

        $seen = [];
        $reverseCounts = [];
        $valid = [];
        foreach ($edges as [$table, $local, $foreignTable, $foreign]) {
            $key = $table.'.'.$local;
            if (isset($seen[$key])
                || ! isset($tables[$table]['columns'][$local])
                || ! isset($tables[$foreignTable]['columns'][$foreign])) {
                continue;
            }
            $seen[$key] = true;
            $valid[] = [$table, $local, $foreignTable, $foreign];
            $reverseCounts[$foreignTable.'<'.$table] = ($reverseCounts[$foreignTable.'<'.$table] ?? 0) + 1;
        }

        foreach ($valid as [$table, $local, $foreignTable, $foreign]) {
            $belongsName = preg_replace('/_id$/', '', $local) ?: $local;
            if (isset($tables[$table]['relations'][$belongsName])) {
                $belongsName = $local;
            }
            $tables[$table]['relations'][$belongsName] = [
                'name' => $belongsName,
                'label' => Str::headline($belongsName).' ('.Str::headline($foreignTable).')',
                'type' => 'belongs_to',
                'table' => $foreignTable,
                'local' => $local,
                'foreign' => $foreign,
            ];

            $hasManyName = ($reverseCounts[$foreignTable.'<'.$table] ?? 1) > 1 || $table === $foreignTable
                ? $table.'_by_'.$local
                : $table;
            $tables[$foreignTable]['relations'][$hasManyName] = [
                'name' => $hasManyName,
                'label' => Str::headline($table).($hasManyName !== $table ? ' (by '.Str::headline($local).')' : ''),
                'type' => 'has_many',
                'table' => $table,
                // For has_many, `local` is the parent's column and `foreign`
                // the child's column that references it.
                'local' => $foreign,
                'foreign' => $local,
            ];
        }

        foreach ($tables as &$table) {
            ksort($table['relations']);
        }
        unset($table);

        return $tables;
    }

    private function deniedColumn(string $column): bool
    {
        foreach ((array) config('reports.deny_column_patterns', []) as $pattern) {
            if (@preg_match((string) $pattern, $column) === 1) {
                return true;
            }
        }

        return false;
    }

    private function columnType(string $type): string
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'bool') => 'boolean',
            str_contains($type, 'int'), str_contains($type, 'decimal'), str_contains($type, 'float'),
            str_contains($type, 'double'), str_contains($type, 'numeric') => 'number',
            $type === 'date' => 'date',
            str_contains($type, 'datetime'), str_contains($type, 'timestamp') => 'datetime',
            $type === 'time' => 'time',
            str_contains($type, 'json') => 'json',
            default => 'text',
        };
    }
}
