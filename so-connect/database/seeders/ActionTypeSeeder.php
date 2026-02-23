<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ActionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create item "Membership Request" - with action code 1
        \App\Models\ActionType::create([
            'action_type_code' => 1,
            'action_type' => 'Membership Request',
        ]);

    }
}
