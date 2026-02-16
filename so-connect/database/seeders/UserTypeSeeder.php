<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userTypes = [
            ['user_type_name' => 'Admin'],
            ['user_type_name' => 'Super Admin'],
            ['user_type_name' => 'Member'],
        ];
        foreach ($userTypes as $type) {

            \App\Models\user_type::create($type);
        }
    }
}
