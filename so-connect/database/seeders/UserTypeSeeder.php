<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // user type codes: 1 = admin, 2 = super_admin, 0 = user without using factory
        \App\Models\UserType::factory()->create([
            'user_type_code' => 1,
            'user_type' => 'admin',
        ]);
        \App\Models\UserType::factory()->create([
            'user_type_code' => 2,
            'user_type' => 'super_admin',
        ]);
        \App\Models\UserType::factory()->create([
            'user_type_code' => 0,
            'user_type' => 'user',
        ]);

    }
}
