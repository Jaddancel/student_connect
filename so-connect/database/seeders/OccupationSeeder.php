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
        $occupations = [
            ['occupation_name' => 'Student'],
            ['occupation_name' => 'Administrative Staff'],
            ['occupation_name' => 'Faculty'],
            ['occupation_name' => 'Alumni'],
            ['occupation_name' => 'Other'],
        ];

        foreach ($occupations as $occupation) {
            \App\Models\occupation::create($occupation);
        }
    }
}
