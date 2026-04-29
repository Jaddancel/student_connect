<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'organization' => null,
            'title' => rtrim($title, '.'),
            'excerpt' => fake()->sentence(18),
            'body' => fake()->paragraphs(3, true),
            'tag' => fake()->randomElement(['Announcement', 'Event', 'Volunteer', 'Workshop', 'Spotlight']),
            'is_featured' => false,
            'published_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn () => [
            'organization' => $organizationId,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => [
            'is_featured' => true,
            'published_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ]);
    }
}
