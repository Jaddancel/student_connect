<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ScoringAuditExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(fn ($row) => [
            $row['org_name'],
            $row['semester'],
            $row['total'],
            $row['scorer_name'],
            $row['scored_at'],
            implode(', ', $row['manual_used'] ?? []),
        ]);
    }

    public function headings(): array
    {
        return [
            'Organization',
            'Semester',
            'Total Score',
            'Scorer',
            'Scored Date',
            'Manual Fields Used',
        ];
    }

    public function title(): string
    {
        return 'Scoring Audit';
    }
}
