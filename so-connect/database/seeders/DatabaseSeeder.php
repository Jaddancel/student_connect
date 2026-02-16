<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            UserTypeSeeder::class,
            OccupationSeeder::class,
            UserSeeder::class,
            // ProfileSeeder::class,
            OrganizationTypeSeeder::class,
            RoleSeeder::class,
            MemberSeeder::class,
            OrganizationSeeder::class,
        ]);
    }
}
