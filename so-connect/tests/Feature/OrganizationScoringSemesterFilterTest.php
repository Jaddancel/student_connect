<?php

use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('submits only the selected semester from the scoring filter', function () {
    $semester = Semester::query()->create([
        'name' => 'First Semester 2026–2027',
        'semester_number' => 1,
        'starts_at' => '2026-08-01',
        'vacation_days' => 30,
    ]);

    $response = $this->actingAs(recordsUser(2))
        ->get(route('admin.scoring.index', ['semester_id' => $semester->semester_id]))
        ->assertOk()
        ->assertViewHas('selectedSemester', $semester);

    expect(substr_count($response->getContent(), 'name="semester_id"'))->toBe(1);
});
