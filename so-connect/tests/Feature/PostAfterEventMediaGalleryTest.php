<?php

use App\Helpers\FormTemplateHelper;
use App\Models\AccomplishmentMedia;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Post;
use App\Models\Request as ActionRequest;
use App\Forms\SystemFunction;
use App\Services\AfterEventMediaGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function galleryOrganization(string $name): int
{
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => $name, 'detail_text' => 'Gallery test organization', 'initials' => 'GT',
    ]);

    return (int) DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);
}

function galleryForm(string $route, ?string $systemFunction = null): Form
{
    $form = Form::create([
        'name' => 'Form '.$route,
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
        'system_function' => $systemFunction,
    ]);

    foreach ([['photo', 'image'], ['attachment', 'file'], ['photos', 'multi-image'], ['note', 'text']] as $i => [$key, $type]) {
        FormDescription::create([
            'form_id' => $form->id, 'field_key' => $key, 'field_label' => ucfirst($key),
            'field_type' => $type, 'is_required' => false, 'field_order' => $i + 1,
        ]);
    }

    return $form;
}

function galleryReportForm(): Form
{
    return galleryForm('after-event-report', SystemFunction::AFTER_EVENT_REPORT);
}

function galleryEvent(int $orgId, string $name): int
{
    $detailId = DB::table('event_details')->insertGetId([
        'name' => $name, 'location' => 'Gym', 'desc_text' => 'Gallery test event',
        'start_time' => now()->subDays(3), 'end_time' => now()->subDays(2),
    ]);

    return (int) DB::table('events')->insertGetId([
        'organization' => $orgId, 'event_detail' => $detailId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function galleryFile(string $path): string
{
    Storage::disk('public')->put($path, 'image-bytes');

    return $path;
}

/**
 * @param  'approved'|'pending'|'rejected'|'president-only'|null  $decision  null = no request filed
 */
function gallerySubmission(Form $form, int $orgId, array $payload, ?string $decision, ?int $eventId = null): FormSubmission
{
    $submission = FormSubmission::query()->create([
        'form_id' => $form->id,
        'organization_id' => $orgId,
        'payload' => $payload,
        'submitted_at' => now(),
    ]);

    if ($eventId !== null) {
        $submission->forceFill(['event_id' => $eventId])->save();
    }

    if ($decision === null) {
        return $submission;
    }

    $request = ActionRequest::query()->create([
        'action' => 'gallery-test',
        'requested_at' => now(),
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'form_id' => $form->id,
        'organization_id' => $orgId,
        'payload' => ['submission_id' => (int) $submission->getKey(), 'form_id' => $form->id],
    ]);

    if ($decision !== 'pending') {
        Approval::query()->create([
            'request' => (int) $request->getKey(),
            'approved_at' => now(),
            'stage' => $decision === 'president-only' ? 'president' : null,
            'is_rejected' => $decision === 'rejected',
        ]);
    }

    return $submission;
}

it('offers only After Event Report images from approved or never-filed submissions', function () {
    $org = galleryOrganization('Gallery Org');
    $report = galleryReportForm();
    $plain = galleryForm('plain-form');
    $otherSystem = galleryForm('new-event', SystemFunction::NEW_EVENT);
    $event = galleryEvent($org, 'Tree Planting');

    gallerySubmission($report, $org, [
        'photo' => galleryFile('form-uploads/after-event-report/approved.jpg'),
        'attachment' => galleryFile('form-uploads/after-event-report/document.pdf'),
        'photos' => [galleryFile('form-uploads/after-event-report/set-1.png'), galleryFile('form-uploads/after-event-report/set-2.webp')],
    ], 'approved', $event);
    gallerySubmission($report, $org, ['photo' => galleryFile('form-uploads/after-event-report/unfiled.jpg')], null);
    gallerySubmission($report, $org, ['photo' => galleryFile('form-uploads/after-event-report/pending.jpg')], 'pending');
    gallerySubmission($report, $org, ['photo' => galleryFile('form-uploads/after-event-report/rejected.jpg')], 'rejected');
    gallerySubmission($report, $org, ['photo' => galleryFile('form-uploads/after-event-report/president.jpg')], 'president-only');
    gallerySubmission($report, $org, ['photo' => 'form-uploads/after-event-report/missing.jpg'], 'approved');
    gallerySubmission($plain, $org, ['photo' => galleryFile('form-uploads/plain-form/plain.jpg')], 'approved');
    gallerySubmission($otherSystem, $org, ['photo' => galleryFile('form-uploads/new-event/system.jpg')], null);

    $items = app(AfterEventMediaGallery::class)->items()->keyBy('path');

    expect($items->keys()->sort()->values()->all())->toBe([
        'form-uploads/after-event-report/approved.jpg',
        'form-uploads/after-event-report/set-1.png',
        'form-uploads/after-event-report/set-2.webp',
        'form-uploads/after-event-report/unfiled.jpg',
    ]);
    expect($items['form-uploads/after-event-report/approved.jpg'])->toMatchArray([
        'status' => 'approved',
        'event' => 'Tree Planting',
        'organization' => 'Gallery Org',
        'organization_id' => $org,
        'field' => 'Photo',
        'url' => '/storage/form-uploads/after-event-report/approved.jpg',
    ]);
    expect($items['form-uploads/after-event-report/unfiled.jpg'])->toMatchArray([
        'status' => 'submitted',
        'event' => 'Untitled event',
    ]);
});

it('is empty when no form is bound to the After Event Report function', function () {
    $org = galleryOrganization('Gallery Org');
    gallerySubmission(galleryForm('plain-form'), $org, ['photo' => galleryFile('form-uploads/plain-form/plain.jpg')], null);

    expect(app(AfterEventMediaGallery::class)->items())->toBeEmpty();
});

it('renders the gallery toggle and items in the posts editor', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    gallerySubmission($form, $org, ['photo' => galleryFile('form-uploads/after-event-report/approved.jpg')], 'approved');

    $this->actingAs(recordsUser(2))
        ->get(route('posts.index'))
        ->assertOk()
        ->assertViewHas('afterEventMediaGallery', fn ($items) => $items->pluck('path')->all() === ['form-uploads/after-event-report/approved.jpg'])
        ->assertSee('After Event gallery');
});

it('publishes a post using an eligible gallery image and rejects ineligible ones', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    $approved = galleryFile('form-uploads/after-event-report/approved.jpg');
    $rejected = galleryFile('form-uploads/after-event-report/rejected.jpg');
    gallerySubmission($form, $org, ['photo' => $approved], 'approved');
    gallerySubmission($form, $org, ['photo' => $rejected], 'rejected');

    $admin = recordsUser(2);
    $fields = ['organization' => $org, 'title' => 'Gallery post', 'excerpt' => 'From the gallery'];

    $this->actingAs($admin)
        ->post(route('posts.store'), $fields + ['image_from_library' => $rejected])
        ->assertSessionHasErrors('image_from_library');

    $plain = galleryFile('form-uploads/plain-form/plain.jpg');
    gallerySubmission(galleryForm('plain-form'), $org, ['photo' => $plain], 'approved');
    $this->actingAs($admin)
        ->post(route('posts.store'), $fields + ['image_from_library' => $plain])
        ->assertSessionHasErrors('image_from_library');

    $this->actingAs($admin)
        ->post(route('posts.store'), $fields + ['image_from_library' => $approved])
        ->assertSessionHasNoErrors();

    expect(Post::query()->where('title', 'Gallery post')->value('image_path'))->toBe($approved);
});

it('never deletes the original form upload when a gallery-backed post changes or is deleted', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    $approved = galleryFile('form-uploads/after-event-report/approved.jpg');
    gallerySubmission($form, $org, ['photo' => $approved], 'approved');

    $post = Post::query()->create([
        'organization' => $org, 'title' => 'Shared', 'excerpt' => 'Shared image',
        'image_path' => $approved, 'status' => 'published', 'published_at' => now(),
    ]);

    $this->actingAs(recordsUser(2))
        ->patch(route('posts.update', $post->post_id), [
            'organization' => $org, 'title' => 'Shared', 'excerpt' => 'Shared image',
            'image' => UploadedFile::fake()->image('replacement.jpg'),
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists($approved);

    $this->actingAs(recordsUser(2))->delete(route('posts.destroy', $post->post_id));

    Storage::disk('public')->assertExists($approved);
});

it('keeps accepting accomplishment library images', function () {
    $org = galleryOrganization('Gallery Org');
    AccomplishmentMedia::query()->create([
        'organization_id' => $org, 'file_path' => 'posts/media/accomplishment/abc-photo.jpg',
        'activity_title' => 'Outreach', 'submitted_at' => now(),
    ]);

    $this->actingAs(recordsUser(2))
        ->post(route('posts.store'), [
            'organization' => $org, 'title' => 'Library post', 'excerpt' => 'From the library',
            'image_from_library' => 'posts/media/accomplishment/abc-photo.jpg',
        ])
        ->assertSessionHasNoErrors();
});

it('publishes several gallery and library picks followed by uploads, in order', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    $first = galleryFile('form-uploads/after-event-report/first.jpg');
    $second = galleryFile('form-uploads/after-event-report/second.jpg');
    gallerySubmission($form, $org, ['photos' => [$first, $second]], 'approved');
    AccomplishmentMedia::query()->create([
        'organization_id' => $org, 'file_path' => 'posts/media/accomplishment/library.jpg',
        'activity_title' => 'Outreach', 'submitted_at' => now(),
    ]);

    $this->actingAs(recordsUser(2))
        ->post(route('posts.store'), [
            'organization' => $org, 'title' => 'Multi', 'excerpt' => 'Many photos', 'image_set' => 1,
            'keep_images' => [$second, 'posts/media/accomplishment/library.jpg', $first],
            'images' => [UploadedFile::fake()->image('upload.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $paths = Post::query()->where('title', 'Multi')->sole()->imagePaths();
    expect($paths)->toHaveCount(4)
        ->and(array_slice($paths, 0, 3))->toBe([$second, 'posts/media/accomplishment/library.jpg', $first])
        ->and($paths[3])->toStartWith('posts/images/');
});

it('reorders, removes, and adds images on edit without deleting shared files', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    $shared = galleryFile('form-uploads/after-event-report/shared.jpg');
    $added = galleryFile('form-uploads/after-event-report/added.jpg');
    gallerySubmission($form, $org, ['photos' => [$shared, $added]], 'approved');
    $owned = galleryFile('posts/images/owned.jpg');
    $removed = galleryFile('posts/images/removed.jpg');

    $post = Post::query()->create([
        'organization' => $org, 'title' => 'Edit', 'excerpt' => 'Edit me',
        'image_path' => $shared, 'image_paths' => [$shared, $owned, $removed],
    ]);
    $fields = ['organization' => $org, 'title' => 'Edit', 'excerpt' => 'Edit me', 'image_set' => 1];

    $this->actingAs(recordsUser(2))
        ->patch(route('posts.update', $post->post_id), $fields + ['keep_images' => [$owned, $added, $shared]])
        ->assertSessionHasNoErrors();

    expect($post->fresh()->imagePaths())->toBe([$owned, $added, $shared])
        ->and($post->fresh()->image_path)->toBe($owned);
    Storage::disk('public')->assertMissing($removed);
    Storage::disk('public')->assertExists([$owned, $shared, $added]);

    $this->patch(route('posts.update', $post->post_id), $fields)->assertSessionHasNoErrors();
    expect($post->fresh()->imagePaths())->toBe([]);
    Storage::disk('public')->assertMissing($owned);
    Storage::disk('public')->assertExists([$shared, $added]);
});

it('rejects ineligible, duplicate, or too many picked images', function () {
    $org = galleryOrganization('Gallery Org');
    $form = galleryReportForm();
    $approved = galleryFile('form-uploads/after-event-report/approved.jpg');
    $rejected = galleryFile('form-uploads/after-event-report/rejected.jpg');
    gallerySubmission($form, $org, ['photo' => $approved], 'approved');
    gallerySubmission($form, $org, ['photo' => $rejected], 'rejected');
    $fields = ['organization' => $org, 'title' => 'Bad', 'excerpt' => 'Bad picks', 'image_set' => 1];
    $admin = recordsUser(2);

    $this->actingAs($admin)->post(route('posts.store'), $fields + ['keep_images' => [$approved, $rejected]])
        ->assertSessionHasErrors('keep_images.1');
    $this->post(route('posts.store'), $fields + ['keep_images' => [$approved, $approved]])
        ->assertSessionHasErrors('keep_images.0');
    $this->post(route('posts.store'), $fields + [
        'keep_images' => [$approved],
        'images' => array_map(fn ($i) => UploadedFile::fake()->image("photo-$i.jpg"), range(1, 20)),
    ])->assertSessionHasErrors('images');

    expect(Post::query()->count())->toBe(0);
});
