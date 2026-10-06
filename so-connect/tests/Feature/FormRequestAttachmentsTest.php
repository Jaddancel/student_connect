<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\Request as ActionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Everything a submission attaches — uploaded photos, uploaded files, photo
 * sets, captured signatures, scanned waivers, and the ID-scan wizard's front /
 * back captures — has to be openable at full size by the admin reviewing the
 * request, not just visible as a thumbnail or printed as a storage path.
 */
function attachmentsForm(): Form
{
    $form = Form::query()->create([
        'name' => 'Field Trip',
        'route_name' => 'attachment-form',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['field_key' => 'purpose', 'field_label' => 'Purpose', 'field_type' => 'text'],
        ['field_key' => 'permit', 'field_label' => 'Permit Letter', 'field_type' => 'file'],
        ['field_key' => 'venue_photo', 'field_label' => 'Venue Photo', 'field_type' => 'image'],
        ['field_key' => 'gallery', 'field_label' => 'Activity Photos', 'field_type' => 'multi-image'],
        ['field_key' => 'student_id', 'field_label' => 'Student ID number', 'field_type' => 'id-scan'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ] as $order => $field) {
        FormDescription::query()->create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    return $form;
}

/** An officer of a fresh organization — builder forms are gated to officers. */
function attachmentsOfficer(): \App\Models\User
{
    $user = recordsUser(3);

    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) recordsOrganization('Attachments Org', 'AO')->getKey(),
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

it('lets the admin open every submitted photo and file at full size', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = attachmentsForm();

    $this->actingAs(attachmentsOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'purpose' => 'Museum visit',
            'permit' => UploadedFile::fake()->create('permit-letter.pdf', 120, 'application/pdf'),
            'venue_photo' => UploadedFile::fake()->image('venue.jpg', 900, 600),
            'gallery' => [
                UploadedFile::fake()->image('one.jpg', 400, 300),
                UploadedFile::fake()->image('two.jpg', 400, 300),
            ],
            'student_id' => '202104832',
            'id_photo_front' => UploadedFile::fake()->image('id-front.jpg', 800, 500),
            'id_photo_back' => UploadedFile::fake()->image('id-back.jpg', 800, 500),
            'sig' => signatureDataUrl(),
        ])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()->where('form_id', $form->id)->firstOrFail();
    $payload = json_decode((string) DB::table('form_submissions')
        ->where('form_submission_id', (int) $actionRequest->payload['submission_id'])
        ->value('payload'), true);

    $response = $this->actingAs(recordsUser(2))
        ->get(route('admin.form-requests.show', [$form, $actionRequest->request_id]))
        ->assertOk();

    // Every stored file is reachable by URL, and every image opens in the
    // layout's lightbox rather than being capped at a thumbnail.
    foreach (['permit', 'venue_photo', 'sig', 'id_photo_front', 'id_photo_back'] as $key) {
        $response->assertSee(Storage::disk('public')->url($payload[$key]), false);
    }
    foreach ($payload['gallery'] as $photo) {
        $response->assertSee(Storage::disk('public')->url($photo), false);
    }

    $response
        ->assertSee('$store.lightbox.show', false)
        // The non-image upload is offered as a labelled, sized download —
        // uploads are stored under a generated name, so the label carries it.
        ->assertSee('Permit Letter')
        ->assertSee('PDF')
        // …and the ID captures, which belong to no field, get their own block.
        ->assertSee('Attachments')
        ->assertSee('Id Photo Front')
        ->assertSee('Id Photo Back')
        // A storage path is never printed as if it were the answer.
        ->assertDontSee('form-uploads/attachment-form/'.basename($payload['venue_photo']).'</p>', false);
});

it('shows a dash for a photo field that was left empty', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = attachmentsForm();

    $this->actingAs(attachmentsOfficer())
        ->post(route('forms.render.submit', $form->route_name), ['purpose' => 'No pictures'])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()->where('form_id', $form->id)->firstOrFail();

    $this->actingAs(recordsUser(2))
        ->get(route('admin.form-requests.show', [$form, $actionRequest->request_id]))
        ->assertOk()
        ->assertSee('Venue Photo')
        ->assertDontSee('$store.lightbox.show', false);
});
