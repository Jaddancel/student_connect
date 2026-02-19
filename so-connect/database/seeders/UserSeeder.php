<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::factory(5)->create();
        \App\Models\User::factory()->admin()->count(5)->create();
        \App\Models\User::factory()->superAdmin()->count(5)->create();
    }
}
