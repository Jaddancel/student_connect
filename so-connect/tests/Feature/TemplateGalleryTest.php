<?php

use App\Models\ContentCategory;
use App\Models\Form;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function galleryFixtures(): array
{
    $activity = ContentCategory::query()->where('slug', 'activity')->firstOrFail();
    $financial = ContentCategory::query()->where('slug', 'financial')->firstOrFail();

    $form = Form::query()->create([
        'name' => 'Community Activity Proposal',
        'description_text' => 'Plan and request a student activity.',
        'route_name' => 'gallery-community-activity',
        'category_id' => $activity->getKey(),
        'is_active' => true,
        'is_published' => true,
        'pdf_template' => ['html' => '<h1>Activity</h1>'],
    ]);

    $report = ReportTemplate::query()->create([
        'name' => 'Activity Monitoring Summary',
        'description' => 'Summarizes monitored activities.',
        'category_id' => $activity->getKey(),
        'audience' => ReportTemplate::AUDIENCE_ADMINS,
        'definition' => ['parameters' => [], 'tokens' => []],
        'is_active' => true,
    ]);

    $other = Form::query()->create([
        'name' => 'Annual Financial Form',
        'route_name' => 'gallery-financial-form',
        'category_id' => $financial->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    return compact('activity', 'financial', 'form', 'report', 'other');
}

it('shows forms and report templates together in a category gallery', function () {
    $fixtures = galleryFixtures();

    $this->actingAs(recordsUser(2))
        ->get(route('admin.template-gallery.index', ['category' => $fixtures['activity']->getKey()]))
        ->assertOk()
        ->assertSee('Community Activity Proposal')
        ->assertSee('Activity Monitoring Summary')
        ->assertDontSee('Annual Financial Form')
        ->assertSee('Online form')
        ->assertSee('Report template');
});

it('filters the template gallery by type and search phrase', function () {
    galleryFixtures();
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->get(route('admin.template-gallery.index', ['type' => 'form']))
        ->assertOk()
        ->assertSee('Community Activity Proposal')
        ->assertDontSee('Activity Monitoring Summary');

    $this->actingAs($admin)
        ->get(route('admin.template-gallery.index', ['q' => 'Monitoring']))
        ->assertOk()
        ->assertSee('Activity Monitoring Summary')
        ->assertDontSee('Community Activity Proposal');
});

it('blocks officers from the administrative template gallery', function () {
    $this->actingAs(recordsUser(3))
        ->get(route('admin.template-gallery.index'))
        ->assertForbidden();
});
