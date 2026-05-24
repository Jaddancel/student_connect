<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class RequestRecordsExport implements WithMultipleSheets
{
    public function __construct(
        private array $accepted,
        private array $pending,
        private array $rejected,
    ) {}

    public function sheets(): array
    {
        return [
            new RequestRecordsSheet('Accepted', $this->accepted),
            new RequestRecordsSheet('Pending', $this->pending),
            new RequestRecordsSheet('Rejected', $this->rejected),
        ];
    }
}

class RequestRecordsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private string $sheetTitle,
        private array $records,
    ) {}

    public function collection(): Collection
    {
        return collect($this->records)->map(fn ($row) => [
            $row['user_id'],
            $row['org_id'],
            $row['request_time'],
            $row['request_type'],
        ]);
    }

    public function headings(): array
    {
        return ['User ID', 'Org ID', 'Request Time', 'Request Type'];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
