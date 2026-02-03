<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        // Build a base acronym from the org name (up to 4 letters)
        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z0-9 ]+/', ' ', $name), -1, PREG_SPLIT_NO_EMPTY);
        $base = collect($words)
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))
            ->implode('');

        $base = Str::upper(Str::substr($base, 0, 4));
        if (Str::length($base) < 2) {
            $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2));
        }
        if ($base === '') {
            $base = Str::upper(fake()->lexify('??'));
        }

        // Ensure uniqueness across this seeding run + existing DB rows
        static $generated = [];
        $initial = $base;
        $tries = 0;

        while (
            in_array($initial, $generated, true) ||
            \App\Models\Organization::query()->where('organization_initial', $initial)->exists()
        ) {
            $tries++;

            // Add suffix until it becomes unique (ABCD, ABCDX, ABCDXY, ABCD12, etc.)
            $suffix = Str::upper(fake()->lexify(str_repeat('?', min(2, $tries))));
            $initial = $base . $suffix;

            if ($tries > 6) {
                $initial = $base . fake()->numberBetween(10, 99);
            }
            if ($tries > 15) {
                $initial = fake()->unique()->regexify('[A-Z]{2,6}');
            }
        }

        $generated[] = $initial;

        // Safe president id (won't crash if administrators table is empty)
        $presidentId = \App\Models\Adminstrator::query()->inRandomOrder()->value('administrator_id');
        if (!$presidentId) {
            $userId = \App\Models\User::query()->inRandomOrder()->value('id')
                ?? \App\Models\User::factory()->create()->id;

            $presidentId = \App\Models\Adminstrator::factory()->create(['user_id' => $userId])->administrator_id;
        }

        return [
            'organization_name' => $name,
            'organization_initial' => $initial,
            'organization_registered_at' => now(),
            'organization_president_id' => $presidentId,
        ];
    }
}
