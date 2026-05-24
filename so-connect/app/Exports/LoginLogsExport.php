<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LoginLogsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private array $logs) {}

    public function collection(): Collection
    {
        return collect($this->logs)->map(fn ($log) => [
            $log['user_email'],
            $log['name'],
            $log['interaction'],
            $log['logged_at'],
        ]);
    }

    public function headings(): array
    {
        return ['Email', 'Name', 'Interaction', 'Logged At'];
    }

    public function title(): string
    {
        return 'Login Activity';
    }
}
