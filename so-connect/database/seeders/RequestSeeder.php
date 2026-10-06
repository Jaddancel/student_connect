<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->count(30)->randomOrgRequest()->create();
        Approval::factory()->count(60)->denyMemberships()->create();
        Approval::factory()->count(120)->approveMemberships()->create();
    }
}
