<?php

namespace Database\Factories\Form;

use App\Models\Form\FormDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormDescription>
 */
class FormDescriptionFactory extends Factory
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
            'field_key' => fake()->unique()->lexify('field_????'),
            'field_label' => fake()->words(3, true),
            'field_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
            'placeholder_hint' => null,
            'field_options' => null,
        ];
    }
}
