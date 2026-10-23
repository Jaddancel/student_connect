<?php

namespace App\Reports;

use App\Enums\OrganizationType;
use App\Forms\SystemFunction;
use App\Models\Semester;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only report sources for the sponsor-required accreditation reports:
 *
 *  - `school_years`: the available school years for the report filter;
 *  - `accredited_organizations`: one row per organization and school year in
 *    which an Organization Accreditation request was approved.
 *
 * Multiple accepted approval rows in the same cycle collapse to the latest
 * approval, so every organization prints once per selected school year.
 */
final class AccreditationReportSources
{
    public const SCHOOL_YEARS = 'school_years';

    public const ORGANIZATION_TYPES = 'organization_types';

    public const ACCREDITED_ORGANIZATIONS = 'accredited_organizations';

    public const ACCREDITED_MEMBERS = 'accredited_organization_members';

    public const ACCREDITED_OFFICERS = 'accredited_organization_officers';

    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private const COLUMNS = [
        self::SCHOOL_YEARS => [
            'school_year' => ['text', 'VARCHAR(20)'],
            'label' => ['text', 'VARCHAR(20)'],
        ],
        self::ORGANIZATION_TYPES => [
            'organization_type' => ['number', 'INT'],
            'label' => ['text', 'VARCHAR(100)'],
        ],
        self::ACCREDITED_ORGANIZATIONS => [
            'accreditation_record_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'school_year' => ['text', 'VARCHAR(20)'],
            'accredited_at' => ['datetime', 'DATETIME'],
            'organization_name' => ['text', 'VARCHAR(255)'],
            'organization_initials' => ['text', 'VARCHAR(100)'],
            'organization_type' => ['number', 'INT'],
        ],
        self::ACCREDITED_MEMBERS => [
            'accreditation_member_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'school_year' => ['text', 'VARCHAR(20)'],
            'organization_name' => ['text', 'VARCHAR(255)'],
            'organization_initials' => ['text', 'VARCHAR(100)'],
            'organization_type' => ['number', 'INT'],
            'first_name' => ['text', 'VARCHAR(255)'],
            'middle_name' => ['text', 'VARCHAR(255)'],
            'last_name' => ['text', 'VARCHAR(255)'],
            'student_id' => ['text', 'VARCHAR(100)'],
            'email' => ['text', 'VARCHAR(255)'],
            'college' => ['text', 'VARCHAR(255)'],
            'course' => ['text', 'VARCHAR(255)'],
            'year_section' => ['text', 'VARCHAR(255)'],
            'member_since' => ['datetime', 'DATETIME'],
        ],
        self::ACCREDITED_OFFICERS => [
            'accreditation_officer_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'school_year' => ['text', 'VARCHAR(20)'],
            'organization_name' => ['text', 'VARCHAR(255)'],
            'organization_initials' => ['text', 'VARCHAR(100)'],
            'organization_type' => ['number', 'INT'],
            'first_name' => ['text', 'VARCHAR(255)'],
            'middle_name' => ['text', 'VARCHAR(255)'],
            'last_name' => ['text', 'VARCHAR(255)'],
            'student_id' => ['text', 'VARCHAR(100)'],
            'email' => ['text', 'VARCHAR(255)'],
            'college' => ['text', 'VARCHAR(255)'],
            'course' => ['text', 'VARCHAR(255)'],
            'position' => ['text', 'VARCHAR(255)'],
            'role' => ['text', 'VARCHAR(50)'],
            'member_since' => ['datetime', 'DATETIME'],
        ],
    ];

    private ?\Illuminate\Database\Eloquent\Collection $semesters = null;

    public function has(string $name): bool
    {
        return isset(self::COLUMNS[$name]);
    }

    public function forget(): void
    {
        $this->semesters = null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $tables
     * @return array<string, array<string, mixed>>
     */
    public function merge(array $tables): array
    {
        if (! isset($tables[self::SCHOOL_YEARS])) {
            $tables[self::SCHOOL_YEARS] = $this->table(
                self::SCHOOL_YEARS,
                'School Years',
                'school_year',
            );
        }

        if (! isset($tables[self::ORGANIZATION_TYPES])) {
            $tables[self::ORGANIZATION_TYPES] = $this->table(
                self::ORGANIZATION_TYPES,
                'Organization Types',
                'organization_type',
            );
        }

        if (! isset($tables[self::ACCREDITED_ORGANIZATIONS])) {
            $tables[self::ACCREDITED_ORGANIZATIONS] = $this->table(
                self::ACCREDITED_ORGANIZATIONS,
                'Accredited Organizations (by school year)',
                'accreditation_record_id',
            );
        }

        foreach ([
            self::ACCREDITED_MEMBERS => ['Accredited Organization Members (by school year)', 'accreditation_member_id'],
            self::ACCREDITED_OFFICERS => ['Accredited Organization Officers (by school year)', 'accreditation_officer_id'],
        ] as $name => [$label, $primary]) {
            if (! isset($tables[$name])) {
                $tables[$name] = $this->table($name, $label, $primary);
            }
        }

        if (isset($tables['organizations']['columns']['organization_id'])) {
            $tables[self::ACCREDITED_ORGANIZATIONS]['relations']['organization'] = [
                'name' => 'organization',
                'label' => 'Organization (Organizations)',
                'type' => 'belongs_to',
                'table' => 'organizations',
                'local' => 'organization_id',
                'foreign' => 'organization_id',
            ];
            $tables['organizations']['relations'][self::ACCREDITED_ORGANIZATIONS] = [
                'name' => self::ACCREDITED_ORGANIZATIONS,
                'label' => 'Accredited Organizations (by school year)',
                'type' => 'has_many',
                'table' => self::ACCREDITED_ORGANIZATIONS,
                'local' => 'organization_id',
                'foreign' => 'organization_id',
            ];
            ksort($tables['organizations']['relations']);
        }

        ksort($tables[self::ACCREDITED_ORGANIZATIONS]['relations']);
        ksort($tables);

        return $tables;
    }

    /**
     * @param  array<string, array<int, mixed>>  $filters
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    public function sql(string $name, array $filters = []): ?array
    {
        if (! $this->has($name)) {
            return null;
        }

        $rows = match ($name) {
            self::SCHOOL_YEARS => $this->schoolYearRows(),
            self::ORGANIZATION_TYPES => $this->organizationTypeRows(),
            self::ACCREDITED_ORGANIZATIONS => $this->accreditationRows($filters),
            self::ACCREDITED_MEMBERS => $this->rosterRows($filters, ['member']),
            self::ACCREDITED_OFFICERS => $this->rosterRows($filters, ['officer', 'president']),
        };

        $columns = [];
        foreach (self::COLUMNS[$name] as $column => [, $sqlType]) {
            $columns[] = '`'.$column.'` '.$sqlType.' PATH \'$."'.$column.'"\' NULL ON EMPTY NULL ON ERROR';
        }

        return [
            'SELECT jt.* FROM JSON_TABLE(CAST(? AS JSON), \'$[*]\' COLUMNS ('.implode(', ', $columns).')) jt',
            [json_encode($rows, JSON_UNESCAPED_UNICODE)],
        ];
    }

    /** @return array<string, mixed> */
    private function table(string $name, string $label, string $primary): array
    {
        $table = [
            'name' => $name,
            'label' => $label,
            'primary' => $primary,
            'columns' => [],
            'relations' => [],
            'virtual' => true,
        ];
        foreach (self::COLUMNS[$name] as $column => [$type]) {
            $table['columns'][$column] = [
                'name' => $column,
                'label' => Str::headline($column),
                'type' => $type,
                'enum' => false,
            ];
        }

        return $table;
    }

    /** @return array<int, array{school_year: string, label: string}> */
    private function schoolYearRows(): array
    {
        $years = collect(Semester::query()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Semester $semester) => $semester->schoolYear())
            ->all())
            ->push(Semester::currentSchoolYear())
            ->merge($this->approvedAccreditations()->map(
                fn (object $approval) => $this->schoolYear(Carbon::parse($approval->approved_at)),
            ))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $years->map(fn (string $year) => ['school_year' => $year, 'label' => $year])->all();
    }

    /** @return array<int, array{organization_type: int, label: string}> */
    private function organizationTypeRows(): array
    {
        return collect(OrganizationType::options())
            ->map(fn (string $label, int|string $type) => ['organization_type' => (int) $type, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, mixed>>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function accreditationRows(array $filters): array
    {
        $allowed = $this->allowed($filters['school_year'] ?? []);
        $types = $this->allowed($filters['organization_type'] ?? []);
        $organizations = $this->allowed($filters['organization_id'] ?? []);

        return $this->approvedAccreditations()
            ->map(function (object $approval): array {
                $approvedAt = Carbon::parse($approval->approved_at);

                return [
                    'accreditation_record_id' => (int) $approval->approval_id,
                    'organization_id' => (int) $approval->organization_id,
                    'school_year' => $this->schoolYear($approvedAt),
                    'accredited_at' => $approvedAt->toDateTimeString(),
                    'organization_name' => (string) ($approval->organization_name ?? ''),
                    'organization_initials' => (string) ($approval->organization_initials ?? ''),
                    'organization_type' => $approval->organization_type,
                ];
            })
            ->when($allowed->isNotEmpty(), fn ($rows) => $rows->filter(
                fn (array $row) => $allowed->has($row['school_year']),
            ))
            ->when($types->isNotEmpty(), fn ($rows) => $rows->filter(
                fn (array $row) => $types->has((string) $row['organization_type']),
            ))
            ->when($organizations->isNotEmpty(), fn ($rows) => $rows->filter(
                fn (array $row) => $organizations->has((string) $row['organization_id']),
            ))
            ->sortBy([['school_year', 'asc'], ['organization_id', 'asc'], ['accredited_at', 'desc']])
            ->unique(fn (array $row) => $row['school_year'].'|'.$row['organization_id'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function allowed(array $values): \Illuminate\Support\Collection
    {
        return collect($values)->map('strval')->filter(fn (string $value) => $value !== '')->flip();
    }

    /**
     * @param  array<string, array<int, mixed>>  $filters
     * @param  array<int, string>  $roles
     * @return array<int, array<string, mixed>>
     */
    private function rosterRows(array $filters, array $roles): array
    {
        $accreditations = collect($this->accreditationRows($filters));
        $organizationIds = $accreditations->pluck('organization_id')->unique()->values();
        if ($organizationIds->isEmpty()) {
            return [];
        }

        $people = DB::table('organization_officers as oo')
            ->leftJoin('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('oo.organization', $organizationIds)
            ->whereIn('oo.role', $roles)
            ->orderBy('oo.organization')
            ->orderBy('p.last_name')
            ->orderBy('p.first_name')
            ->get([
                'oo.org_officer_id', 'oo.organization', 'oo.role', 'oo.position', 'oo.member_since',
                'u.user_email', 'p.first_name', 'p.middle_name', 'p.last_name', 'p.student_id',
                'p.college', 'p.course', 'p.year_section',
            ])
            ->groupBy('organization');

        $rows = [];
        foreach ($accreditations as $accreditation) {
            foreach ($people->get($accreditation['organization_id'], collect()) as $person) {
                $row = [
                    'organization_id' => $accreditation['organization_id'],
                    'school_year' => $accreditation['school_year'],
                    'organization_name' => $accreditation['organization_name'],
                    'organization_initials' => $accreditation['organization_initials'],
                    'organization_type' => $accreditation['organization_type'],
                    'first_name' => (string) ($person->first_name ?? ''),
                    'middle_name' => (string) ($person->middle_name ?? ''),
                    'last_name' => (string) ($person->last_name ?? ''),
                    'student_id' => (string) ($person->student_id ?? ''),
                    'email' => (string) ($person->user_email ?? ''),
                    'college' => (string) ($person->college ?? ''),
                    'course' => (string) ($person->course ?? ''),
                    'member_since' => $person->member_since !== null
                        ? Carbon::parse($person->member_since)->toDateTimeString()
                        : null,
                ];
                if ($roles === ['member']) {
                    $row['accreditation_member_id'] = ((int) $accreditation['accreditation_record_id'] * 1000000) + (int) $person->org_officer_id;
                    $row['year_section'] = (string) ($person->year_section ?? '');
                } else {
                    $row['accreditation_officer_id'] = ((int) $accreditation['accreditation_record_id'] * 1000000) + (int) $person->org_officer_id;
                    $row['position'] = (string) ($person->position ?? '');
                    $row['role'] = (string) ($person->role ?? '');
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function approvedAccreditations(): \Illuminate\Support\Collection
    {
        return DB::table('approvals as a')
            ->join('requests as r', 'r.request_id', '=', 'a.request')
            ->join('forms as f', 'f.id', '=', 'r.form_id')
            ->join('organizations as o', 'o.organization_id', '=', 'r.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('f.system_function', SystemFunction::ORG_ACCREDITATION)
            ->where('a.is_rejected', false)
            ->whereNotNull('r.organization_id')
            ->orderByDesc('a.approved_at')
            ->orderByDesc('a.approval_id')
            ->get([
                'a.approval_id', 'a.approved_at', 'r.organization_id', 'o.organization_type',
                'od.name as organization_name', 'od.initials as organization_initials',
            ]);
    }

    private function schoolYear(Carbon $date): string
    {
        $semester = $this->semesters()
            ->filter(fn (Semester $semester) => $semester->activePeriodStart()->lte($date))
            ->sortByDesc('starts_at')
            ->first();
        if ($semester instanceof Semester) {
            return $semester->schoolYear();
        }

        $year = (int) $date->format('Y');
        $start = (int) $date->format('n') >= 8 ? $year : $year - 1;

        return $start.'–'.($start + 1);
    }

    private function semesters(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->semesters ??= Semester::query()->orderBy('starts_at')->get();
    }
}
