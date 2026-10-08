<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SeedData::at('2023-06-01 08:00:00', fn () => SeedData::user(User::TYPE_SUPERADMIN, 'superadmin@example.com'));
    }
}
