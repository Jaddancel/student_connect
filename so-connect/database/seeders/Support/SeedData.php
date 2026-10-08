<?php

namespace Database\Seeders\Support;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SeedData
{
    private static ?string $password = null;

    public const START = '2023-06-01';

    public const END = '2026-10-31 23:59:59';

    public static function at(string $date, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse($date));
        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    public static function user(int $type = User::TYPE_OFFICER, ?string $email = null): User
    {
        $profile = Profile::factory()->create([
            'occupation' => $type <= User::TYPE_ADMIN ? 'Staff' : 'Student',
            'course' => fake()->randomElement(['BS Computer Science', 'BS Agriculture', 'BS Education']),
            'year_section' => fake()->randomElement(['1-A', '2-B', '3-A', '4-B']),
            'birthplace' => 'Camiling, Tarlac',
            'home_address' => fake()->address(),
            'parents_guardian' => fake()->name(),
            'talents_hobbies' => fake()->randomElement(['Music', 'Debate', 'Sports', 'Painting']),
            'student_id' => fake()->unique()->numerify('#########'),
            'origin' => Profile::ORIGIN_REGISTERED,
        ]);
        $profile->update([
            'photo' => self::portrait(),
            'signature_path' => 'seed-signatures/profile-'.$profile->getKey().'.jpg',
        ]);

        return User::query()->create([
            'profile' => $profile->getKey(),
            'user_email' => $email ?? fake()->unique()->safeEmail(),
            'user_type' => $type,
            'user_password' => self::$password ??= Hash::make('tAU100!!'),
            'user_created_at' => now(),
            'email_verified_at' => now(),
        ]);
    }

    /** @return list<string> */
    public static function images(string $folder): array
    {
        $path = rtrim(config('seeding.assets_path'), '/').'/'.$folder;
        $images = is_dir($path)
            ? array_values(array_filter(File::files($path), fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)))
            : [];
        if ($images === []) {
            throw new RuntimeException("No seed images found in {$path}. Set SEED_ASSETS_PATH to the .seedfiles directory.");
        }

        return array_map(fn ($file) => $file->getPathname(), $images);
    }

    public static function copyImage(string $source, string $directory): string
    {
        $path = $directory.'/'.sha1_file($source).'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $disk = Storage::disk('public');
        if (! $disk->exists($path) && ! $disk->put($path, file_get_contents($source))) {
            throw new RuntimeException("Unable to store seed image {$path}.");
        }

        return $path;
    }

    public static function portrait(): string
    {
        $root = rtrim(config('seeding.assets_path'), '/');
        $folder = is_dir($root.'/portraits') ? 'portraits' : 'portrait';

        return self::copyImage(fake()->randomElement(self::images($folder)), 'seed-portraits');
    }

    /** @return list<string> */
    public static function eventPhotos(): array
    {
        $images = self::images('event_photo');
        if (count($images) < 5) {
            throw new RuntimeException('The seed event_photo directory must contain at least five images.');
        }

        return array_map(
            fn ($source) => self::copyImage($source, 'form-uploads/seed-events'),
            fake()->randomElements($images, fake()->numberBetween(2, 5)),
        );
    }

    /** @return list<string> */
    public static function statuses(int $acceptedPercent): array
    {
        $accepted = (int) (50 * $acceptedPercent / 100);

        return fake()->shuffleArray([
            ...array_fill(0, $accepted, 'accepted'),
            ...array_fill(0, 5, 'declined'),
            ...array_fill(0, 45 - $accepted, 'pending'),
        ]);
    }

    /** @return list<string> */
    public static function burstDates(): array
    {
        $dates = [];
        $months = [];
        for ($month = Carbon::parse(self::START); $month->lte(Carbon::parse(self::END)); $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }
        $months = fake()->shuffleArray($months);
        while (count($dates) < 50) {
            foreach ($months as $month) {
                $start = Carbon::parse($month.'-01');
                $firstDay = $month === '2023-06' ? 4 : 1;
                $day = $start->copy()->day(fake()->numberBetween($firstDay, $start->daysInMonth))->setTime(9, 0);
                $count = (fake()->boolean(80) ? fake()->numberBetween(5, 8) : 0)
                    + (fake()->boolean(30) ? fake()->numberBetween(12, 15) : 0);
                for ($i = 0; $i < $count && count($dates) < 50; $i++) {
                    $dates[] = $day->copy()->addMinutes($i * 3)->toDateTimeString();
                }
                if (count($dates) === 50) {
                    break;
                }
            }
        }
        sort($dates);

        return $dates;
    }
}
