<?php

use App\Forms\FieldType;
use App\Models\AccomplishmentMedia;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Services\AccomplishmentAttachmentLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function libraryOfficer(string $email, string $orgName): array
{
    $user = recordsUser(3, ['email' => $email]);
    $org = recordsOrganization($orgName, strtoupper(substr($orgName, 0, 2)));
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $org->getKey(), 'user' => $user->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    return [$user, $org];
}

it('scopes the attachment library to the active officers organization', function () {
    Storage::fake('public');
    [$officer, $ownOrg] = libraryOfficer('library-own@example.test', 'Own Org');
    [, $otherOrg] = libraryOfficer('library-other@example.test', 'Other Org');
    Storage::disk('public')->put('posts/media/accomplishment/own.jpg', 'own');
    Storage::disk('public')->put('posts/media/accomplishment/other.jpg', 'other');

    AccomplishmentMedia::query()->create([
        'organization_id' => $ownOrg->getKey(), 'file_path' => 'posts/media/accomplishment/own.jpg',
        'activity_title' => 'Tree Planting', 'tags' => ['environment'], 'submitted_at' => now(),
    ]);
    AccomplishmentMedia::query()->create([
        'organization_id' => $otherOrg->getKey(), 'file_path' => 'posts/media/accomplishment/other.jpg',
        'activity_title' => 'Private Event', 'tags' => ['private'], 'submitted_at' => now(),
    ]);

    $this->actingAs($officer)->get(route('attachment-library.index'))
        ->assertOk()->assertSee('Tree Planting')->assertDontSee('Private Event');

    expect(app(AccomplishmentAttachmentLibrary::class)->allows($officer, 'posts/media/accomplishment/own.jpg'))->toBeTrue()
        ->and(app(AccomplishmentAttachmentLibrary::class)->allows($officer, 'posts/media/accomplishment/other.jpg'))->toBeFalse();
});

it('lets an officer tag an attachment from their own organization only', function () {
    Storage::fake('public');
    [$officer, $org] = libraryOfficer('library-tags@example.test', 'Tag Org');
    Storage::disk('public')->put('posts/media/accomplishment/tag.jpg', 'tag');
    $media = AccomplishmentMedia::query()->create([
        'organization_id' => $org->getKey(), 'file_path' => 'posts/media/accomplishment/tag.jpg',
        'activity_title' => 'Old title', 'submitted_at' => now(),
    ]);

    $this->actingAs($officer)->put(route('attachment-library.update', $media), [
        'activity_title' => 'Community Outreach',
        'tags' => 'Outreach, Community, outreach',
    ])->assertRedirect();

    expect($media->fresh()->activity_title)->toBe('Community Outreach')
        ->and($media->fresh()->tags)->toBe(['outreach', 'community']);
});

it('reuses an indexed attachment in a new accomplishment report without another upload', function () {
    Storage::fake('public');
    [$officer, $org] = libraryOfficer('library-reuse@example.test', 'Reuse Org');
    $path = 'posts/media/accomplishment/reusable.jpg';
    Storage::disk('public')->put($path, 'image');
    AccomplishmentMedia::query()->create([
        'organization_id' => $org->getKey(), 'file_path' => $path,
        'activity_title' => 'Reusable Evidence', 'tags' => ['outreach'], 'submitted_at' => now(),
    ]);

    $form = Form::query()->create([
        'name' => 'Accomplishment Report', 'route_name' => 'accomplishment-report',
        'is_active' => true, 'is_published' => true,
        'pdf_template' => ['html' => '<h1>Report</h1>'],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->getKey(), 'field_key' => 'photos', 'field_label' => 'Documentation',
        'field_type' => FieldType::MULTI_IMAGE, 'is_required' => true, 'field_order' => 1,
        'field_options' => ['max_files' => 5, 'media_copy' => 'accomplishment'],
    ]);

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'photos_library' => [$path],
    ])->assertRedirect(route('forms.render', $form->route_name));

    expect(FormSubmission::query()->where('form_id', $form->getKey())->sole()->payload['photos'])
        ->toBe([$path])
        ->and(AccomplishmentMedia::query()->where('file_path', $path)->count())->toBe(1);
});
