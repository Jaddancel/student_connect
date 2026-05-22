<?php

namespace Database\Seeders;

use App\Models\Officer;
use App\Models\President;
use Illuminate\Database\Seeder;

class PresidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Officer::query()
            ->where('position', 'President')
            ->each(function (Officer $officer) {
                President::create(['officer' => $officer->org_officer_id]);
            });
    }
}
