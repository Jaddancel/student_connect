<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = ['academic', 'socio-civic', 'religious', 'fraternities-sororities', 'special interest'];
        foreach ($types as $type) {
            \App\Models\organization_type::create([
                'organization_type' => $type
            ]);
        }
    }
}
