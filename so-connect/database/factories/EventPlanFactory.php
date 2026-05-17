<?php

namespace Database\Factories;

use App\Models\EventPlan;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPlan>
 */
class EventPlanFactory extends Factory
{
    protected $model = EventPlan::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::query()->inRandomOrder()->value('organization_id') ?? Organization::factory(),
            'created_by' => User::query()->inRandomOrder()->value('user_id') ?? User::factory(),
            'title' => $this->faker->sentence(4, false),
            'target_date' => $this->faker->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
            'resources_needed' => $this->faker->optional(0.7)->sentence(10),
            'persons_responsible' => [],
            'status' => 'pending',
            'event_id' => null,
            'request_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }

    public function junked(): static
    {
        return $this->state(['status' => 'junked']);
    }
}
