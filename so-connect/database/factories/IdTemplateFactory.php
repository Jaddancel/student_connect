<?php

namespace Database\Factories;

use App\Models\IdTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdTemplate>
 */
class IdTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(4, true).' ID Template',
            'image_path' => 'id-templates/front.jpg',
            'image_width' => 1000,
            'image_height' => 600,
            'zones' => [[
                'name' => 'student_id',
                'label' => 'Student ID Number',
                'x1' => 100,
                'y1' => 300,
                'x2' => 500,
                'y2' => 360,
                'regex' => '\\d{2}-\\d{4}-\\d{3}',
                'field' => 'student_id',
            ]],
            'back_image_path' => 'id-templates/back.jpg',
            'back_image_width' => 1000,
            'back_image_height' => 600,
            'back_zones' => [[
                'name' => 'home_address',
                'label' => 'Home Address',
                'x1' => 50,
                'y1' => 100,
                'x2' => 600,
                'y2' => 200,
                'regex' => null,
                'field' => 'home_address',
            ]],
            'orientation' => 'vertical',
            'is_active' => true,
            'is_default' => true,
            'created_by' => null,
        ];
    }
}