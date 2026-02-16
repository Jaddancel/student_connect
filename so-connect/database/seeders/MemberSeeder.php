<?php

namespace Database\Seeders;

use App;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create ten members and five admins
        \App\Models\Member::factory()->count(10)->create();
        \App\Models\Member::factory()->admin()->count(5)->create();
    }
}
