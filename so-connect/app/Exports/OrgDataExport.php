<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrgDataExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private Collection $organizations) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->organizations as $org) {
            $officers = $org['officers'] ?? [];

            if (empty($officers)) {
                $rows->push([
                    $org['organization_id'],
                    $org['name'],
                    $org['initials'],
                    $org['organization_type'],
                    null,
                    null,
                    null,
                    null,
                    null,
                ]);
            } else {
                foreach ($officers as $officer) {
                    $rows->push([
                        $org['organization_id'],
                        $org['name'],
                        $org['initials'],
                        $org['organization_type'],
                        $officer['org_officer_id'],
                        $officer['name'],
                        $officer['role'],
                        $officer['position'],
                        $officer['member_since'],
                    ]);
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Org ID',
            'Name',
            'Initials',
            'Type',
            'Officer ID',
            'Officer Name',
            'Role',
            'Position',
            'Member Since',
        ];
    }

    public function title(): string
    {
        return 'Organizations';
    }
}
