<?php

use App\Models\ActionLog;
use App\Models\OrganizationScore;
use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Models\ScoringEvidenceAward;
use App\Models\Semester;
use App\Services\Scoring\EvidenceQualityEvaluator;
use App\Services\Scoring\ScoringRubricVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates an immutable rubric version whenever scoring configuration changes', function () {
    ScoringCategory::query()->create(['key' => 'test', 'label' => 'Test', 'cap' => 100, 'sort_order' => 1]);
    $criterion = ScoringCriterion::query()->create([
        'key' => 'quality_test', 'category_key' => 'test', 'label' => 'Quality test',
        'weight' => 5, 'sort_order' => 1, 'is_system' => false, 'is_active' => true,
    ]);

    $service = app(ScoringRubricVersionService::class);
    $first = $service->current();
    $criterion->update(['weight' => 10]);
    $second = $service->current();

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($first->content_hash)->not->toBe($second->content_hash)
        ->and(data_get($first->snapshot, 'criteria.0.weight'))->toBe(5)
        ->and(data_get($second->snapshot, 'criteria.0.weight'))->toBe(10);
});

it('flags weak evidence with an explainable quality result', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    Storage::disk('public')->put('evidence/tiny.pdf', 'short');

    $result = app(EvidenceQualityEvaluator::class)->evaluate(
        ['title' => 'Only one completed field'],
        ['evidence/tiny.pdf'],
    );

    expect($result['status'])->toBe('needs_review')
        ->and($result['score'])->toBe(60)
        ->and(implode(' ', $result['notes']))->toContain('smaller than 50 KB')
        ->toContain('fewer than four completed data fields');
});

it('requires a reason for overrides and records a reviewed adjustment in the audit log', function () {
    $admin = recordsUser(2);
    $organization = recordsOrganization('Evidence Organization');
    $semester = Semester::query()->create([
        'name' => 'Evidence Semester', 'semester_number' => 1,
        'starts_at' => '2026-08-01', 'ends_at' => '2026-12-20', 'vacation_days' => 0,
    ]);
    $score = OrganizationScore::query()->create([
        'organization_id' => $organization->getKey(), 'semester_id' => $semester->getKey(),
        'scored_by' => $admin->getKey(), 'payload' => [], 'raw_scores' => ['total' => 10],
        'total_weighted_score' => 10, 'scored_at' => now(),
    ]);
    $evidence = ScoringEvidenceAward::query()->create([
        'organization_score_id' => $score->getKey(), 'criterion_key' => 'quality_test',
        'criterion_label' => 'Quality test', 'instances' => 1, 'rule_points' => 10, 'awarded_points' => 10,
        'source_type' => 'form_submission', 'source_id' => 12, 'evidence_label' => 'Report #12',
        'quality_status' => 'needs_review', 'review_status' => 'pending',
    ]);

    $this->actingAs($admin)->patch(route('admin.scoring.evidence.review', [$score, $evidence]), [
        'review_status' => 'verified', 'override_points' => 4,
    ])->assertSessionHasErrors('override_reason');

    $this->actingAs($admin)->patch(route('admin.scoring.evidence.review', [$score, $evidence]), [
        'review_status' => 'verified', 'override_points' => 4, 'override_reason' => 'Only part of the report was supported.',
    ])->assertRedirect();

    expect((float) $score->fresh()->total_weighted_score)->toBe(4.0)
        ->and((float) $score->fresh()->raw_scores['manual_adjustment'])->toBe(-6.0)
        ->and(ActionLog::query()->where('action', 'evidence_reviewed')->exists())->toBeTrue();
});
