<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = [
            // random company name
            'organization_name' => fake()->company(),
            'organization_initials' => fake()->lexify('???'),
            'president_id' => null,
            // get one organization type from table
            'organization_type_code' => \App\Models\organization_type::query()->inRandomOrder()->first()->getKey(),
        ];
        \App\Models\Organization::query()->create($organization);
    }
}
