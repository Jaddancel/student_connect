<?php

use App\Models\Evaluation;
use App\Models\Semester;
use App\Services\StudentRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function studentRankingMembership(int $organizationId, string $firstName, string $role = 'member'): array
{
    $user = recordsUser(3, [
        'first_name' => $firstName,
        'middle_name' => '',
        'last_name' => 'Student',
        'student_id' => 'SID-'.$firstName,
        'course' => 'BSIT',
        'year_section' => '3A',
    ]);

    $membershipId = DB::table('organization_officers')->insertGetId([
        'role' => $role,
        'position' => $role === 'member' ? null : 'Officer',
        'organization' => $organizationId,
        'user' => $user->getKey(),
        'member_since' => '2026-08-01 00:00:00',
        'registered_at' => '2026-08-01 00:00:00',
    ], 'org_officer_id');

    return [$user, $membershipId];
}

function studentRankingSemester(): Semester
{
    return Semester::query()->create([
        'name' => 'First Semester 2026–2027',
        'semester_number' => 1,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-12-20',
        'vacation_days' => 30,
    ]);
}

it('records an audited student evaluation and validates that its date belongs to the semester', function () {
    $semester = studentRankingSemester();
    $organization = recordsOrganization('Student Leaders Association', 'SLA');
    [$student, $membershipId] = studentRankingMembership((int) $organization->getKey(), 'Ana', 'officer');
    $admin = recordsUser(2);

    $this->actingAs($admin)->post(route('admin.student-rankings.store'), [
        'semester_id' => $semester->getKey(),
        'org_officer_id' => $membershipId,
        'score' => '92.5',
        'description' => 'Led the semester outreach project.',
        'evaluated_at' => '2026-10-15',
    ])->assertRedirect(route('admin.student-rankings.index', ['semester_id' => $semester->getKey()]));

    $this->assertDatabaseHas('evaluations', [
        'author' => $membershipId,
        'score' => '92.50',
        'description' => 'Led the semester outreach project.',
    ]);
    $this->assertDatabaseHas('action_logs', [
        'user_id' => $admin->getKey(),
        'category' => 'scoring',
        'action' => 'student_evaluation_saved',
    ]);

    $this->actingAs($admin)->from(route('admin.student-rankings.index'))->post(route('admin.student-rankings.store'), [
        'semester_id' => $semester->getKey(),
        'org_officer_id' => $membershipId,
        'score' => 85,
        'description' => 'Outside the chosen semester.',
        'evaluated_at' => '2026-07-31',
    ])->assertRedirect(route('admin.student-rankings.index'))
        ->assertSessionHasErrors('evaluated_at');

    expect(Evaluation::query()->count())->toBe(1)
        ->and((int) $student->getKey())->toBeGreaterThan(0);
});

it('ranks individual students by semester average and assigns transparent levels', function () {
    $semester = studentRankingSemester();
    $organization = recordsOrganization('Academic Society', 'AS');
    [, $anaMembership] = studentRankingMembership((int) $organization->getKey(), 'Ana', 'president');
    [, $benMembership] = studentRankingMembership((int) $organization->getKey(), 'Ben');
    [, $caraMembership] = studentRankingMembership((int) $organization->getKey(), 'Cara');

    foreach ([
        [$anaMembership, 90, '2026-09-01'],
        [$anaMembership, 80, '2026-10-01'],
        [$benMembership, 85, '2026-11-01'],
        [$caraMembership, 100, '2027-01-15'],
    ] as [$membership, $score, $date]) {
        Evaluation::query()->create([
            'author' => $membership,
            'score' => $score,
            'description' => 'Student performance evaluation',
            'evaluated_at' => $date,
        ]);
    }

    $rows = app(StudentRankingService::class)->rankings($semester);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'rank' => 1,
            'name' => 'Ana Student',
            'evaluation_count' => 2,
            'total_score' => 170.0,
            'average_score' => 85.0,
        ])
        ->and($rows[0]['level'])->toMatchArray(['number' => 3, 'label' => 'Advanced'])
        ->and($rows[1])->toMatchArray([
            'rank' => 2,
            'name' => 'Ben Student',
            'evaluation_count' => 1,
            'average_score' => 85.0,
        ]);

    $response = $this->actingAs(recordsUser(2))
        ->get(route('admin.student-rankings.index', ['semester_id' => $semester->getKey()]))
        ->assertOk()
        ->assertSee('Individual Student Leaderboard')
        ->assertSee('Ana Student')
        ->assertSee('Level 3');

    $response->assertViewHas('rows', fn ($viewRows) => $viewRows->pluck('name')->all() === ['Ana Student', 'Ben Student']);
});

it('restricts student ranking management to administrators', function () {
    $semester = studentRankingSemester();

    $this->actingAs(recordsUser(3))
        ->get(route('admin.student-rankings.index', ['semester_id' => $semester->getKey()]))
        ->assertForbidden();
});
