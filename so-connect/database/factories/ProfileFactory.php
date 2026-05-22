<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $birthday = fake()->dateTimeBetween('-30 years', '-16 years');
        $sex = fake()->randomElement(['Male', 'Female']);

        return [
            'first_name' => fake()->filipinoFirstName($sex),
            'middle_name' => fake()->filipinoFirstName(),
            'last_name' => fake()->filipinoLastName(),
            'contact_number' => '9' . fake()->numerify('#########'),
            'age' => (int) $birthday->diff(new \DateTime)->y,
            'sex' => $sex,
            'religion' => fake()->randomElement(['Roman Catholic', 'Islam', 'Born-Again Christian', 'Iglesia ni Cristo', 'Other']),
            'nationality' => fake()->randomElement(['Filipino', 'Filipino']),
            'birthday' => $birthday->format('Y-m-d'),
            'course_year' => fake()->randomElement([
                'BSCS - 1st Year', 'BSCS - 2nd Year', 'BSCS - 3rd Year', 'BSCS - 4th Year',
                'BSED - 1st Year', 'BSED - 2nd Year', 'BSED - 3rd Year', 'BSED - 4th Year',
                'BSN - 1st Year', 'BSN - 2nd Year', 'BSN - 3rd Year', 'BSN - 4th Year',
                'BSBA - 1st Year', 'BSBA - 2nd Year', 'BSBA - 3rd Year', 'BSBA - 4th Year',
                'BSCE - 1st Year', 'BSCE - 2nd Year', 'BSCE - 3rd Year', 'BSCE - 4th Year',
            ]),
            'occupation' => fake()->randomElement(['Other', 'Student', 'Faculty']),
            'address' => \App\Models\Profile\profileAddress::factory()->create()->getKey(),
        ];
    }

    public function twentyUnassigned()
    {
        return $this->count(20);
    }
}
