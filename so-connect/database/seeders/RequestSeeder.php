<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->count(30)->whoAppliesForRequest()->create();
    }
}
