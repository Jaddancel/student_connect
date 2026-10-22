<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\Officer;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the picked workplan\'s approved activities as "name - date" on the accreditation form', function () {
    $organization = recordsOrganization('Accredited Society');
    $officer = recordsUser(3);
    Officer::query()->create([
        'organization' => $organization->getKey(),
        'user' => $officer->getKey(),
        'role' => 'president',
        'position' => 'President',
    ]);

    $semester = Semester::query()->create([
        'name' => '1st Semester AY 2026-2027', 'semester_number' => 1,
        'starts_at' => '2026-08-01', 'ends_at' => '2026-12-15', 'vacation_days' => 30,
    ]);
    $workplan = Workplan::query()->create([
        'organization_id' => $organization->getKey(),
        'semester_id' => $semester->getKey(),
        'status' => 'finalized',
    ]);

    $plan = fn (string $title, string $date, string $status = 'approved') => EventPlan::query()->create([
        'organization_id' => $organization->getKey(), 'created_by' => $officer->getKey(),
        'title' => $title, 'target_date' => $date, 'status' => $status, 'parent_plan_id' => null,
    ]);
    $plan('Acquaintance Party', '2026-09-05');
    $plan('Tree Planting', '2026-10-12');
    $plan('Pending Seminar', '2026-11-03', 'pending');
    $plan('Last Year Outreach', '2025-10-01');

    $form = Form::query()->create([
        'name' => 'Organization Accreditation', 'route_name' => 'org-accreditation-test',
        'system_function' => SystemFunction::ORG_ACCREDITATION, 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'workplan_id', 'field_label' => 'Workplan',
        'field_type' => FieldType::WORKPLAN_SELECT, 'field_order' => 1,
    ]);

    $html = $this->actingAs($officer)->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('x-data="formWorkplanSelect(', false)
        ->assertSee('x-show="isPicked('.$workplan->getKey().')"', false)
        ->assertSeeInOrder([
            'Approved activities:',
            'Acquaintance Party - September 5, 2026',
            'Tree Planting - October 12, 2026',
        ])
        ->getContent();

    expect($html)->not->toContain('Pending Seminar')
        ->and($html)->not->toContain('Last Year Outreach');
});
