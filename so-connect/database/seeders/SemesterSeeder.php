<?php

namespace Database\Seeders;

use App\Models\Semester;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        SeedData::at(SeedData::START, function () {
            for ($year = 2023; $year <= 2030; $year++) {
                foreach ([1, 2] as $number) {
                    $start = $year.($number === 1 ? '-06-22' : '-11-09');
                    Semester::query()->updateOrCreate(['starts_at' => $start], [
                        'name' => ($number === 1 ? 'First' : 'Second').' Semester '.$year.'-'.($year + 1),
                        'semester_number' => $number,
                        'ends_at' => $number === 1 ? $year.'-11-02' : ($year + 1).'-03-29',
                        'vacation_days' => $number === 1 ? 84 : 6,
                        'created_by' => null,
                    ]);
                }
            }
        });
    }
}
