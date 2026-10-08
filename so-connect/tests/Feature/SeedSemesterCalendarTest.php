<?php

use App\Models\Semester;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('seeds the requested semester calendar through 2030 including vacation gaps', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(SemesterSeeder::class);

    expect(Semester::query()->count())->toBe(16);
    foreach (range(2023, 2030) as $year) {
        $first = Semester::query()->whereDate('starts_at', $year.'-06-22')->firstOrFail();
        $second = Semester::query()->whereDate('starts_at', $year.'-11-09')->firstOrFail();
        expect($first->endsAt()->toDateString())->toBe($year.'-11-02')
            ->and($second->endsAt()->toDateString())->toBe(($year + 1).'-03-29')
            ->and($first->schoolYear())->toBe($year.'–'.($year + 1))
            ->and($second->schoolYear())->toBe($year.'–'.($year + 1))
            ->and($first->activePeriodStart()->toDateString())->toBe($year.'-03-30')
            ->and($second->activePeriodStart()->toDateString())->toBe($year.'-11-03');
    }

    Carbon::setTestNow('2026-11-02');
    try {
        expect(Semester::query()->whereDate('starts_at', '2026-06-22')->firstOrFail()->isCurrentPeriod())->toBeTrue();
        Carbon::setTestNow('2026-11-03');
        expect(Semester::query()->whereDate('starts_at', '2026-06-22')->firstOrFail()->isCurrentPeriod())->toBeFalse();
        expect(Semester::currentlyActive()->starts_at->toDateString())->toBe('2026-11-09');
    } finally {
        Carbon::setTestNow();
    }
});

it('preserves next-semester-derived ends for existing calendars without explicit ends', function () {
    $first = Semester::query()->create([
        'name' => 'First', 'semester_number' => 1, 'starts_at' => '2025-08-25', 'vacation_days' => 30,
    ]);
    $second = Semester::query()->create([
        'name' => 'Second', 'semester_number' => 2, 'starts_at' => '2026-01-22', 'vacation_days' => 30,
    ]);
    expect($first->endsAt()->toDateString())->toBe('2026-01-21')
        ->and($second->endsAt())->toBeNull()
        ->and($first->schoolYear())->toBe('2025–2026')
        ->and($second->schoolYear())->toBe('2025–2026');
});
