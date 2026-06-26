<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createActivityWaiverAdmin(string $email): User
{
    return User::query()->create([
        'user_email' => $email,
        'user_password' => 'password',
        'user_type' => 2, // admin — skips officer org-authorization in store()
        'profile' => null,
    ]);
}

function seedActivityRequestFormWithTemplate(User $admin): array
{
    // A superadmin must exist or EnsureSystemInitialized redirects to /setup.
    User::query()->firstOrCreate(
        ['user_email' => 'activity-waiver-superadmin@example.test'],
        ['user_password' => 'password', 'user_type' => 1, 'profile' => null],
    );

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    $form = Form::query()->create([
        'name' => 'Request for Organizational Activity',
        'route_name' => 'activity-request',
        'description_text' => 'Activity request form',
        'created_by' => (int) $admin->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    // An active template is required for the form.template middleware to pass.
    // Its docx is intentionally absent so generation fails gracefully after the
    // waiver has already been stored.
    Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => null,
        'uploaded_by' => (int) $admin->getKey(),
        'template_name' => 'Activity Template',
        'docx_path' => 'form-templates/missing-activity-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    return [$form, $organization];
}

function validActivityRequestPayload(Organization $organization): array
{
    return [
        'organization_id' => (int) $organization->getKey(),
        'organization' => 'Test Organization',
        'date' => '2026-07-01',
        'projectActivity' => 'Sports Fest',
        'purposed' => 'To promote camaraderie among students.',
        'time' => '8:00 AM - 5:00 PM',
        'placeAndVenue' => 'TAU Gymnasium',
        'presidentName' => 'Juan Dela Cruz',
        'presidentContactNo' => '09171234567',
        'adviserRow' => ['Prof. Maria Santos'],
    ];
}

it('rejects an activity request submitted without a parent/guardian waiver', function () {
    Storage::fake('public');

    $admin = createActivityWaiverAdmin('activity-waiver-missing@example.test');
    [, $organization] = seedActivityRequestFormWithTemplate($admin);

    $this->actingAs($admin)
        ->post(route('activity-request.store'), validActivityRequestPayload($organization))
        ->assertSessionHasErrors('parentGuardianWaiver');

    expect(FormSubmission::query()->count())->toBe(0);
});

it('stores the uploaded waiver and records its path on the submission', function () {
    Storage::fake('public');

    $admin = createActivityWaiverAdmin('activity-waiver-stored@example.test');
    [$form, $organization] = seedActivityRequestFormWithTemplate($admin);

    $payload = validActivityRequestPayload($organization);
    $payload['parentGuardianWaiver'] = UploadedFile::fake()->create('signed-waiver.pdf', 200, 'application/pdf');

    $this->actingAs($admin)
        ->post(route('activity-request.store'), $payload)
        ->assertRedirect();

    $submission = FormSubmission::query()
        ->where('form_id', (int) $form->getKey())
        ->where('organization_id', (int) $organization->getKey())
        ->firstOrFail();

    $waiverPath = $submission->payload['parentGuardianWaiver'] ?? null;

    expect($waiverPath)->toBeString()
        ->and($waiverPath)->toStartWith('activity-waivers/'.$submission->getKey().'/');

    Storage::disk('public')->assertExists($waiverPath);
});

it('rejects a waiver file with a disallowed type', function () {
    Storage::fake('public');

    $admin = createActivityWaiverAdmin('activity-waiver-badtype@example.test');
    [, $organization] = seedActivityRequestFormWithTemplate($admin);

    $payload = validActivityRequestPayload($organization);
    $payload['parentGuardianWaiver'] = UploadedFile::fake()->create('waiver.txt', 10, 'text/plain');

    $this->actingAs($admin)
        ->post(route('activity-request.store'), $payload)
        ->assertSessionHasErrors('parentGuardianWaiver');

    expect(FormSubmission::query()->count())->toBe(0);
});
