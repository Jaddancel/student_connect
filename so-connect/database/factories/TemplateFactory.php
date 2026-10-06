<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
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
            'uploaded_by' => null,
            'template_name' => fake()->words(3, true).' Template',
            'docx_path' => 'templates/'.fake()->uuid().'.docx',
            'version' => 1,
            'is_active' => true,
        ];
    }
}
