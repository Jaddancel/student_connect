<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->regular()->create();
        $this->call(OrganizationSeeder::class);
        $this->call(ProfileSeeder::class);
        $this->call(RequestSeeder::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(FormPageSeeder::class);
        $this->call(EventPlanSeeder::class);
        $this->call(SemesterSeeder::class);
    }
}
