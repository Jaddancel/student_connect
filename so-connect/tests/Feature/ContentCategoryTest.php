<?php

use App\Models\ContentCategory;
use App\Models\Form;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the required purpose categories', function () {
    expect(ContentCategory::query()->ordered()->pluck('name')->all())->toBe([
        'Membership', 'Accreditation', 'Activity', 'Financial', 'Accomplishment', 'Recognition',
    ]);
});

it('allows admins to manage categories and records their presentation fields', function () {
    $response = $this->actingAs(recordsUser(2))->post(route('admin.categories.store'), [
        'name' => 'Community Service',
        'description' => 'Outreach templates',
        'icon' => 'task',
        'color' => 'success',
        'display_order' => 20,
        'is_active' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('content_categories', [
        'name' => 'Community Service', 'slug' => 'community-service',
        'is_active' => true, 'display_order' => 20,
    ]);
});

it('does not delete a category that is assigned', function () {
    $category = ContentCategory::query()->where('slug', 'activity')->firstOrFail();
    Form::query()->create([
        'name' => 'Activity Proposal', 'route_name' => 'activity-proposal',
        'category_id' => $category->id, 'is_active' => true, 'is_published' => true,
    ]);
    ReportTemplate::query()->create([
        'name' => 'Activity Summary', 'category_id' => $category->id,
        'audience' => ReportTemplate::AUDIENCE_ADMINS, 'definition' => [], 'is_active' => true,
    ]);

    $this->actingAs(recordsUser(2))->delete(route('admin.categories.destroy', $category))
        ->assertSessionHas('error');
    $this->assertDatabaseHas('content_categories', ['id' => $category->id]);
});

it('blocks non-admin users from category management', function () {
    $this->actingAs(recordsUser(3))->get(route('admin.categories.index'))->assertForbidden();
});
