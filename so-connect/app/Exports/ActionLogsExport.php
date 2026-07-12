<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ActionLogsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private array $logs) {}

    public function collection(): Collection
    {
        return collect($this->logs)->map(fn ($log) => [
            $log['created_at'],
            $log['user_email'],
            $log['name'],
            $log['category_label'],
            $log['action'],
            $log['description'],
            $log['meta'] !== [] ? json_encode($log['meta']) : '',
        ]);
    }

    public function headings(): array
    {
        return ['Time', 'Email', 'Name', 'Category', 'Action', 'Description', 'Details'];
    }

    public function title(): string
    {
        return 'Administrator Actions';
    }
}
