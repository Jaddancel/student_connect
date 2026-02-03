<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Database\Factories\OrganizationFactory;
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
            AdminSeeder::class,
            OrganizationSeeder::class,
            ApprovalSeeder::class,
            MembershipSeeder::class,
        ]);
    }
}
