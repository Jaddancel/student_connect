<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Event\eventDetails;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Event::factory()
            ->count(10)
            ->has(eventDetails::factory(), 'details')
            ->create();
    }
}
