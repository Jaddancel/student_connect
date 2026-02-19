<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Member\Role::create([
            'role_name' => 'member',
        ]);
        \App\Models\Member\Role::create([
            'role_name' => 'president',
        ]);
    }
}
