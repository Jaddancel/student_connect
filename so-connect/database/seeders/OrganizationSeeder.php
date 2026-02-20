<?php

namespace Database\Seeders;

use App\Models\Member\President;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        President::factory(10)->create();
        \App\Models\Member::factory(20)->create();
    }
}
