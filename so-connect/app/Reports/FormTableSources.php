<?php

namespace App\Reports;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Services\AfterEventReportService;
use Illuminate\Support\Str;

/**
 * Exposes the rows of a form's table-input field to report tokens as a
 * read-only virtual table (`reports.form_tables`), so the builder's groups,
 * aggregates, filters and parameters work on them like on any other table.
 *
 * Each row of the table becomes a table row carrying its submission,
 * organization and the field's columns. The scope decides which submissions
 * contribute:
 *
 *  - `latest_approved`: each organization's latest approved submission only;
 *  - `current_semester`: every submission filed for an event of the running
 *    semester (approval is not required), with the event's name, dates and
 *    location alongside.
 *
 * The payload is cast back to JSON because MySQL rejects a derived table's
 * payload column as a JSON_TABLE source otherwise.
 *
 * Field and column keys are interpolated into SQL, so only keys matching
 * {@see KEY_PATTERN} are ever used.
 */
final class FormTableSources
{
    public const KEY_PATTERN = '/^[A-Za-z0-9_]{1,64}$/';

    private const MAX_ROWS_PER_SUBMISSION = 9999;

    /** @var array<string, array<string, mixed>|null> */
    private array $resolved = [];

    /**
     * Catalog entries for every configured source whose form and field exist.
     *
     * @return array<string, array<string, mixed>>
     */
    public function tables(): array
    {
        $tables = [];
        foreach (array_keys((array) config('reports.form_tables', [])) as $name) {
            $source = $this->source((string) $name);
            if ($source !== null) {
                $tables[(string) $name] = $source['table'];
            }
        }

        return $tables;
    }

    public function has(string $name): bool
    {
        return $this->source($name) !== null;
    }

    /**
     * The derived table's SQL (aliasable as a FROM source) and its bindings.
     *
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    public function sql(string $name): ?array
    {
        $source = $this->source($name);

        return $source === null ? null : [$source['sql'], $source['bindings']];
    }

    /**
     * @return array{table: array<string, mixed>, sql: string, bindings: array<int, mixed>}|null
     */
    private function source(string $name): ?array
    {
        if (array_key_exists($name, $this->resolved)) {
            return $this->resolved[$name];
        }

        try {
            return $this->resolved[$name] = $this->build($name, (array) (config('reports.form_tables')[$name] ?? []));
        } catch (\Illuminate\Database\QueryException) {
            // The forms schema is not there yet (e.g. mid-migration).
            return $this->resolved[$name] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{table: array<string, mixed>, sql: string, bindings: array<int, mixed>}|null
     */
    private function build(string $name, array $config): ?array
    {
        $form = $this->form((array) ($config['form'] ?? []));
        $fieldKey = (string) ($config['field'] ?? '');
        if ($form === null || ! preg_match(self::KEY_PATTERN, $fieldKey)) {
            return null;
        }

        $field = $form->fields->first(fn ($f) => $f->field_key === $fieldKey && $f->field_type === FieldType::TABLE_INPUT);
        if ($field === null) {
            return null;
        }

        $options = (array) ($field->field_options ?? []);
        $scope = (string) ($config['scope'] ?? 'latest_approved');
        [$scopeSql, $bindings, $extras] = $scope === 'current_semester'
            ? $this->currentSemesterScope((int) $form->getKey())
            : $this->latestApprovedScope((int) $form->getKey());

        $columns = [
            'row_id' => 'number',
            'submission_id' => 'number',
            'organization_id' => 'number',
            'row_no' => 'number',
            'submitted_at' => 'datetime',
        ] + $extras['types'];

        $jsonColumns = [];
        $fieldSelect = [];
        $fieldColumns = [];
        foreach (FieldType::tableColumns($options) as $column) {
            $fieldColumns[$column['key']] = $column['type'] === 'number' ? 'number' : 'text';
        }
        $rowTotal = FieldType::tableRowTotal($options);
        if ($rowTotal !== null) {
            $fieldColumns[$rowTotal['key']] = 'number';
        }
        foreach ($fieldColumns as $key => $type) {
            if (isset($columns[$key]) || ! preg_match(self::KEY_PATTERN, (string) $key) || $this->denied((string) $key)) {
                continue;
            }
            $columns[$key] = $type;
            $fieldSelect[] = 'jt.`'.$key.'` AS `'.$key.'`';
            $definition = $type === 'number' ? 'DECIMAL(18,4)' : 'VARCHAR(500)';
            $jsonColumns[] = '`'.$key.'` '.$definition.' PATH \'$."'.$key.'"\' NULL ON EMPTY NULL ON ERROR';
        }

        $select = [
            '(s.form_submission_id * '.(self::MAX_ROWS_PER_SUBMISSION + 1).' + jt.row_no) AS row_id',
            's.form_submission_id AS submission_id',
            's.organization_id AS organization_id',
            'jt.row_no AS row_no',
            's.submitted_at AS submitted_at',
        ];
        foreach (array_keys($extras['types']) as $extra) {
            $select[] = 's.`'.$extra.'` AS `'.$extra.'`';
        }
        $select = array_merge($select, $fieldSelect);

        $sql = 'SELECT '.implode(', ', $select)
            .' FROM ('.$scopeSql.') s'
            .' JOIN JSON_TABLE(CAST(s.payload AS JSON), \'$."'.$fieldKey.'"[*]\' COLUMNS (row_no FOR ORDINALITY'
            .($jsonColumns !== [] ? ', '.implode(', ', $jsonColumns) : '').')) jt'
            .' WHERE jt.row_no <= '.self::MAX_ROWS_PER_SUBMISSION;

        $table = [
            'name' => $name,
            'label' => (string) ($config['label'] ?? Str::headline($name)),
            'primary' => 'row_id',
            'columns' => [],
            'relations' => [],
            'virtual' => true,
        ];
        foreach ($columns as $column => $type) {
            $table['columns'][$column] = [
                'name' => $column,
                'label' => Str::headline($column),
                'type' => $type,
                'enum' => false,
            ];
        }

        return ['table' => $table, 'sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * @param  array<string, mixed>  $reference  `route_name` or `system_function`
     */
    private function form(array $reference): ?Form
    {
        if (($reference['system_function'] ?? '') !== '') {
            return SystemFunction::form((string) $reference['system_function'])?->load('fields');
        }
        if (($reference['route_name'] ?? '') !== '') {
            return Form::query()->where('route_name', (string) $reference['route_name'])->first()?->load('fields');
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>, 2: array{types: array<string, string>}}
     */
    private function latestApprovedScope(int $formId): array
    {
        $sql = 'SELECT ranked.form_submission_id, ranked.organization_id, ranked.submitted_at, ranked.payload FROM ('
            .'SELECT fs.form_submission_id, fs.organization_id, fs.submitted_at, fs.payload,'
            .' ROW_NUMBER() OVER (PARTITION BY fs.organization_id ORDER BY approved.approved_at DESC, fs.form_submission_id DESC) AS rn'
            .' FROM form_submissions fs'
            .' JOIN requests r ON r.form_id = fs.form_id'
            .' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(r.payload, \'$.submission_id\')) AS UNSIGNED) = fs.form_submission_id'
            .' JOIN (SELECT request, MAX(approved_at) AS approved_at FROM approvals GROUP BY request HAVING SUM(is_rejected) = 0) approved'
            .' ON approved.request = r.request_id'
            .' WHERE fs.form_id = ? AND fs.organization_id IS NOT NULL'
            .') ranked WHERE ranked.rn = 1';

        return [$sql, [$formId], ['types' => []]];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>, 2: array{types: array<string, string>}}
     */
    private function currentSemesterScope(int $formId): array
    {
        $window = app(AfterEventReportService::class)->semesterWindow();
        $when = 'COALESCE(ed.end_time, ed.start_time, fs.submitted_at)';

        $sql = 'SELECT fs.form_submission_id, fs.organization_id, fs.submitted_at, fs.payload,'
            .' ed.name AS event_name, ed.location AS event_location, ed.start_time AS event_start, ed.end_time AS event_end'
            .' FROM form_submissions fs'
            .' LEFT JOIN events e ON e.event_id = fs.event_id'
            .' LEFT JOIN event_details ed ON ed.event_detail_id = e.event_detail'
            .' WHERE fs.form_id = ? AND fs.organization_id IS NOT NULL';
        $bindings = [$formId];

        if ($window['start'] !== null) {
            $sql .= ' AND '.$when.' >= ?';
            $bindings[] = $window['start']->toDateTimeString();
        }
        if ($window['end'] !== null) {
            $sql .= ' AND '.$when.' <= ?';
            $bindings[] = $window['end']->toDateTimeString();
        }

        return [$sql, $bindings, ['types' => [
            'event_name' => 'text',
            'event_location' => 'text',
            'event_start' => 'datetime',
            'event_end' => 'datetime',
        ]]];
    }

    private function denied(string $column): bool
    {
        foreach ((array) config('reports.deny_column_patterns', []) as $pattern) {
            if (@preg_match((string) $pattern, $column) === 1) {
                return true;
            }
        }

        return false;
    }
}
