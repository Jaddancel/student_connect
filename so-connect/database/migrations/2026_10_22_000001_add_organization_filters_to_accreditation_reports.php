<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REPORTS = [
        'Accredited School Organizations',
        'Members of Accredited Organizations',
        'Officers of Accredited Organizations',
    ];

    public function up(): void
    {
        foreach (DB::table('report_templates')->whereIn('name', self::REPORTS)->get(['id', 'definition']) as $row) {
            $definition = json_decode((string) $row->definition, true);
            if (! is_array($definition)) {
                continue;
            }

            $parameters = (array) ($definition['parameters'] ?? []);
            $names = array_column($parameters, 'name');
            if (in_array('organization_type', $names, true) || in_array('organization', $names, true)) {
                continue;
            }

            $definition['parameters'] = [...$parameters, [
                'name' => 'organization_type',
                'label' => 'Organization Type',
                'type' => 'entity',
                'entity' => 'organization_types',
                'display' => ['label'],
                'required' => false,
            ], [
                'name' => 'organization',
                'label' => 'Organization',
                'type' => 'entity',
                'entity' => 'organizations',
                'display' => ['detail', 'name'],
                'required' => false,
            ]];
            $definition['tokens'] = array_map(fn ($token) => $this->withFilters($token), (array) ($definition['tokens'] ?? []));

            DB::table('report_templates')->where('id', $row->id)->update([
                'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('report_templates')->whereIn('name', self::REPORTS)->get(['id', 'definition']) as $row) {
            $definition = json_decode((string) $row->definition, true);
            if (! is_array($definition)) {
                continue;
            }

            $definition['parameters'] = array_values(array_filter(
                (array) ($definition['parameters'] ?? []),
                fn ($parameter) => ! in_array($parameter['name'] ?? null, ['organization_type', 'organization'], true),
            ));
            $definition['tokens'] = array_map(fn ($token) => $this->withoutFilters($token), (array) ($definition['tokens'] ?? []));

            DB::table('report_templates')->where('id', $row->id)->update([
                'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    /** Adds the organization filters to every token that is filtered by school year (the report's data sources). */
    private function withFilters(array $token): array
    {
        $where = (array) ($token['where'] ?? []);
        $bySchoolYear = array_filter($where, fn ($c) => ($c['column'] ?? null) === 'school_year' && ($c['param'] ?? null) === 'school_year');
        $isSource = in_array($token['from'] ?? $token['entity'] ?? null, [
            'accredited_organizations', 'accredited_organization_members', 'accredited_organization_officers',
        ], true);

        if ($bySchoolYear !== [] && $isSource) {
            $token['where'] = [...$where,
                ['column' => 'organization_type', 'op' => '=', 'param' => 'organization_type'],
                ['column' => 'organization_id', 'op' => '=', 'param' => 'organization'],
            ];
        }

        return $token;
    }

    private function withoutFilters(array $token): array
    {
        if (isset($token['where'])) {
            $token['where'] = array_values(array_filter(
                (array) $token['where'],
                fn ($c) => ! in_array($c['param'] ?? null, ['organization_type', 'organization'], true),
            ));
        }

        return $token;
    }
};
