<?php

namespace Database\Factories;

use App\Models\Approval;
use App\Models\Event\EventDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_id' => Approval::factory(),
            'event_detail' => EventDetail::factory(),
        ];
    }
}
