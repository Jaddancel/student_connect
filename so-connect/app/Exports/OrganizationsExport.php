<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrganizationsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private array $organizations) {}

    public function collection(): Collection
    {
        return collect($this->organizations)->map(fn ($org) => [
            $org['organization_id'],
            $org['name'],
            $org['initials'],
            $org['officer_count'],
        ]);
    }

    public function headings(): array
    {
        return ['Org ID', 'Name', 'Initials', 'Officer Count'];
    }

    public function title(): string
    {
        return 'Organizations';
    }
}
