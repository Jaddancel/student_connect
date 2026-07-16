<?php

namespace Database\Seeders;

use App\Models\IdTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (IdTemplate::query()->exists()) {
            return;
        }

        IdTemplate::factory()->create();
    }
}
