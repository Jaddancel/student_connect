<?php

namespace Database\Factories;

use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
class RequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => null,
            'requested_at' => now(),
            'user' => null,
            'action_type' => null,
            'request_type_id' => null,
            'organization_id' => null,
            'requested_by' => null,
            'payload' => [],
        ];
    }
}
