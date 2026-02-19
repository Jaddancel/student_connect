<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OccupationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // base occupations
        \App\Models\Profile\Occupation::query()->updateOrCreate(
            ['occupation_code' => 1],
            ['occupation_name' => 'student']
        );

        \App\Models\Profile\Occupation::query()->updateOrCreate(
            ['occupation_code' => 2],
            ['occupation_name' => 'faculty']
        );

        \App\Models\Profile\Occupation::query()->updateOrCreate(
            ['occupation_code' => 3],
            ['occupation_name' => 'staff']
        );

        \App\Models\Profile\Occupation::query()->updateOrCreate(
            ['occupation_code' => 4],
            ['occupation_name' => 'guest']
        );
    }
}
