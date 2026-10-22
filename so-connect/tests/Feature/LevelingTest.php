<?php

use App\Models\LevelHistory;
use App\Models\LevelThreshold;
use App\Models\OrganizationScore;
use App\Models\Semester;
use App\Services\LevelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function levelingSemester(): Semester
{
    return Semester::query()->create([
        'name' => 'Level History Semester', 'semester_number' => 1,
        'starts_at' => '2026-08-01', 'ends_at' => '2026-12-20', 'vacation_days' => 0,
    ]);
}

it('normalizes organization totals and stores a semester level snapshot', function () {
    $organization = recordsOrganization('Excellent Organization', 'EO');
    $semester = levelingSemester();
    $score = OrganizationScore::query()->create([
        'organization_id' => $organization->getKey(), 'semester_id' => $semester->getKey(),
        'payload' => [], 'raw_scores' => ['total' => 495], 'total_weighted_score' => 495, 'scored_at' => now(),
    ]);

    $level = app(LevelingService::class)->organizationLevel(495);
    app(LevelingService::class)->recordOrganization($score, $semester);

    expect($level)->toMatchArray(['number' => 4, 'label' => 'Excellent', 'normalized_score' => 90.0]);
    $this->assertDatabaseHas('level_histories', [
        'scope' => 'organization', 'semester_id' => $semester->getKey(),
        'subject_id' => $organization->getKey(), 'level_number' => 4,
        'level_label' => 'Excellent', 'normalized_score' => '90.00',
    ]);
});

it('allows administrators to configure thresholds and audits the change', function () {
    $admin = recordsUser(2);
    $payload = LevelThreshold::query()->get()->mapWithKeys(fn (LevelThreshold $threshold) => [
        $threshold->getKey() => [
            'label' => $threshold->scope === 'organization' && $threshold->level_number === 4
                ? 'Exceptional'
                : $threshold->label,
            'minimum_score' => $threshold->scope === 'organization' && $threshold->level_number === 4
                ? 95
                : (float) $threshold->minimum_score,
        ],
    ])->all();

    $this->actingAs($admin)->put(route('admin.levels.update'), ['thresholds' => $payload])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(app(LevelingService::class)->organizationLevel(495))->toMatchArray([
        'number' => 3, 'label' => 'Very Satisfactory', 'normalized_score' => 90.0,
    ]);
    $this->assertDatabaseHas('level_thresholds', [
        'scope' => 'organization', 'level_number' => 4, 'label' => 'Exceptional', 'minimum_score' => '95.00',
    ]);
    $this->assertDatabaseHas('action_logs', [
        'user_id' => $admin->getKey(), 'category' => 'scoring_config', 'action' => 'level_thresholds_updated',
    ]);
});

it('keeps organization and student levels separate across semester history', function () {
    $semester = levelingSemester();
    $organization = recordsOrganization('History Organization', 'HO');
    $score = OrganizationScore::query()->create([
        'organization_id' => $organization->getKey(), 'semester_id' => $semester->getKey(),
        'payload' => [], 'raw_scores' => ['total' => 440], 'total_weighted_score' => 440, 'scored_at' => now(),
    ]);
    $student = recordsUser(3, ['first_name' => 'Level', 'last_name' => 'Student']);

    $levels = app(LevelingService::class);
    $levels->recordOrganization($score, $semester);
    $levels->recordStudent($semester, ['user_id' => $student->getKey(), 'average_score' => 72]);

    expect(LevelHistory::query()->where('semester_id', $semester->getKey())->count())->toBe(2)
        ->and(LevelHistory::query()->where('scope', 'organization')->value('level_number'))->toBe(3)
        ->and(LevelHistory::query()->where('scope', 'student')->value('level_number'))->toBe(2);

    $this->actingAs(recordsUser(2))->get(route('admin.levels.index', ['semester_id' => $semester->getKey()]))
        ->assertOk()->assertSee('History Organization')->assertSee('Level Student');
});
