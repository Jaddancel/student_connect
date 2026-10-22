<?php

use App\Models\BestOrganizationCandidate;
use App\Models\BestOrganizationCycle;
use App\Models\OrganizationScore;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bestOrganizationSemester(): Semester
{
    return Semester::query()->create([
        'name' => 'First Semester 2026–2027', 'semester_number' => 1,
        'starts_at' => '2026-08-01', 'ends_at' => '2026-12-20', 'vacation_days' => 0,
    ]);
}

it('runs the formal best organization workflow from verified scores to published award', function () {
    $admin = recordsUser(2);
    $semester = bestOrganizationSemester();
    $first = recordsOrganization('Alpha Organization', 'ALP');
    $second = recordsOrganization('Beta Organization', 'BET');
    foreach ([[$first, 96.5], [$second, 91.0]] as [$organization, $total]) {
        OrganizationScore::query()->create([
            'organization_id' => $organization->getKey(), 'semester_id' => $semester->getKey(),
            'payload' => [], 'raw_scores' => [], 'total_weighted_score' => $total, 'scored_by' => $admin->getKey(), 'scored_at' => now(),
        ]);
    }

    $this->actingAs($admin)->post(route('admin.best-organizations.store'), [
        'semester_id' => $semester->getKey(), 'title' => 'Best Student Organization 2026',
    ])->assertRedirect();
    $cycle = BestOrganizationCycle::query()->firstOrFail();

    $this->actingAs($admin)->post(route('admin.best-organizations.sync', $cycle))->assertSessionHas('success');
    expect($cycle->candidates()->count())->toBe(2)
        ->and((float) $cycle->candidates()->orderBy('rank_snapshot')->first()->score_snapshot)->toBe(96.5);

    $this->actingAs($admin)->post(route('admin.best-organizations.start', $cycle))->assertSessionHas('success');
    $finalist = $cycle->candidates()->where('organization_id', $first->getKey())->firstOrFail();
    $this->actingAs($admin)->patch(route('admin.best-organizations.candidates.review', [$cycle, $finalist]), [
        'status' => BestOrganizationCandidate::FINALIST, 'review_notes' => 'Verified all scoring documents.',
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.best-organizations.finalize', $cycle), [
        'candidate_id' => $finalist->id,
        'selection_notes' => 'The committee verified the score, submitted evidence, and final ranking.',
    ])->assertSessionHas('success');

    $cycle->refresh();
    expect($cycle->status)->toBe(BestOrganizationCycle::AWARDED)
        ->and($cycle->winner_organization_id)->toBe($first->getKey())
        ->and($cycle->winner_name)->toBe('Alpha Organization')
        ->and((float) $cycle->winner_score)->toBe(96.5);

    $this->actingAs($admin)->get(route('best-organizations.index'))
        ->assertOk()->assertSee('Alpha Organization')->assertDontSee('Beta Organization');
});

it('requires a finalist and decision rationale before an award can be finalized', function () {
    $admin = recordsUser(2);
    $semester = bestOrganizationSemester();
    $cycle = BestOrganizationCycle::create(['semester_id' => $semester->getKey(), 'title' => 'Award', 'status' => BestOrganizationCycle::EVALUATION, 'created_by' => $admin->getKey()]);

    $this->actingAs($admin)->post(route('admin.best-organizations.finalize', $cycle), [
        'candidate_id' => 999, 'selection_notes' => 'Too short',
    ])->assertSessionHasErrors(['candidate_id', 'selection_notes']);
});

it('blocks officers from award administration', function () {
    $this->actingAs(recordsUser(3))->get(route('admin.best-organizations.index'))->assertForbidden();
});
