<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SeedData::at('2023-06-01 08:00:00', fn () => SeedData::superadminFromAccountForm('superadmin@example.com'));
    }
}
