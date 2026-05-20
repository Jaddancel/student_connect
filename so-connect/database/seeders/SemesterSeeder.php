<?php

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        $semesters = [
            [
                'name'            => 'First Semester 2025–2026',
                'semester_number' => 1,
                'starts_at'       => '2025-08-25',
                'vacation_days'   => 30,
                'created_by'      => null,
            ],
            [
                'name'            => 'Second Semester 2025–2026',
                'semester_number' => 2,
                'starts_at'       => '2026-01-22',
                'vacation_days'   => 30,
                'created_by'      => null,
            ],
        ];

        foreach ($semesters as $data) {
            Semester::query()->firstOrCreate(
                ['starts_at' => $data['starts_at']],
                $data,
            );
        }
    }
}
