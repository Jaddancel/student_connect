<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => null,
            'organization_id' => null,
            'submitted_by' => null,
            'payload' => [
                'field_1' => fake()->sentence(),
            ],
            'submitted_at' => now(),
        ];
    }
}
