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
            UserSeeder::class,
            OrganizationSeeder::class,
<<<<<<< HEAD
            MemberSeeder::class,
=======
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
            EventSeeder::class,
        ]);
    }
}
