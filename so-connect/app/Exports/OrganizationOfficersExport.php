<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrganizationOfficersExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{organization: string, name: string, email: string, role: string, position: string, member_since: string}>  $officers
     */
    public function __construct(private array $officers) {}

    public function collection(): Collection
    {
        return collect($this->officers)->map(fn ($officer) => [
            $officer['organization'],
            $officer['name'],
            $officer['email'],
            $officer['role'],
            $officer['position'],
            $officer['member_since'],
        ]);
    }

    public function headings(): array
    {
        return ['Organization', 'Name', 'Email', 'Role', 'Position', 'Member Since'];
    }

    public function title(): string
    {
        return 'Officers';
    }
}
