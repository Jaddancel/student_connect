<?php

namespace Database\Factories\Template;

use App\Models\Template\TemplateDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateDescription>
 */
class TemplateDescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => null,
            'form_description_id' => null,
            'placeholder_key' => fake()->unique()->lexify('field_????'),
            'field_key' => fake()->unique()->lexify('field_????'),
            'is_required' => true,
        ];
    }
}
