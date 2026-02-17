<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['member', 'president'];
        foreach ($roles as $role) {
            \App\Models\role::create(['role' => $role]);
        }
    }
}
