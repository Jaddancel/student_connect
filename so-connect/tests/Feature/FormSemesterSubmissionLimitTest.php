<?php

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function limitTestUser(int $type, string $tag): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines', 'province' => 'Tarlac', 'town' => 'Camiling', 'barangay' => 'Poblacion',
    ]);
    $profile = Profile::query()->create([
        'first_name' => 'Limit', 'last_name' => 'Tester', 'middle_name' => 'T', 'occupation' => 'Staff', 'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => 'limit-'.$tag.'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

function limitTestOfficer(string $tag): User
{
    $user = limitTestUser(3, $tag);
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Limit Org '.$tag, 'detail_text' => 'Test organization', 'initials' => 'LO',
    ]);
    $orgId = DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $orgId, 'user' => (int) $user->getKey(), 'yearterm' => null,
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    return $user;
}

function limitTestForm(?int $limit, ?string $systemFunction = null): Form
{
    $form = Form::create([
        'name' => 'Limited Form',
        'route_name' => 'limited-form',
        'is_active' => true,
        'is_published' => true,
        'semester_submission_limit' => $limit,
        'system_function' => $systemFunction,
        'layout' => ['rows' => [['columns' => [['span' => 12, 'fields' => ['note']]]]]],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="note" contenteditable="false">Note</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'note', 'field_label' => 'Note',
        'field_type' => 'text', 'is_required' => false, 'field_order' => 1,
    ]);

    return $form;
}

/** A request filed against the form with an optional decision. */
function limitTestRequest(Form $form, Carbon $requestedAt, ?bool $approved, ?string $stage = null): void
{
    $request = ActionRequest::query()->create([
        'action' => 'limit-test',
        'requested_at' => $requestedAt,
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'form_id' => $form->id,
        'payload' => [],
    ]);

    if ($approved !== null) {
        Approval::query()->create([
            'request' => $request->getKey(),
            'approved_at' => $requestedAt,
            'stage' => $stage,
            'is_rejected' => ! $approved,
        ]);
    }
}

function limitTestSemesters(): Semester
{
    Semester::create(['name' => 'Previous', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDays(200), 'vacation_days' => 0]);

    return Semester::create(['name' => 'Current', 'semester_number' => 2, 'starts_at' => Carbon::today()->subDays(30), 'vacation_days' => 0]);
}

it('saves, clears and validates the semester submission limit from the builder', function () {
    $admin = limitTestUser(2, 'admin');
    $payload = [
        'name' => 'Capped', 'route_name' => 'capped-form',
        'fields' => [['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false]],
        'rows' => [],
        'semester_submission_limit' => 3,
    ];

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload)->assertOk();
    $form = Form::where('route_name', 'capped-form')->firstOrFail();
    expect($form->semester_submission_limit)->toBe(3);

    $this->putJson(route('admin.form-builder.update', $form), array_merge($payload, ['semester_submission_limit' => null]))
        ->assertOk();
    expect($form->fresh()->semester_submission_limit)->toBeNull();

    $this->putJson(route('admin.form-builder.update', $form), array_merge($payload, ['semester_submission_limit' => 0]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('semester_submission_limit');
});

it('counts only approved submissions in the current semester across all organizations', function () {
    limitTestSemesters();
    $form = limitTestForm(2);

    limitTestRequest($form, Carbon::now()->subDays(5), true);
    limitTestRequest($form, Carbon::now()->subDays(4), null);                 // pending
    limitTestRequest($form, Carbon::now()->subDays(3), false);                // rejected
    limitTestRequest($form, Carbon::now()->subDays(2), true, 'president');    // non-final stage
    limitTestRequest($form, Carbon::now()->subDays(100), true);               // previous semester

    $status = \App\Forms\SemesterSubmissionLimit::status($form);
    expect($status['accepted'])->toBe(1)
        ->and($status['remaining'])->toBe(1)
        ->and($status['reached'])->toBeFalse();
});

it('lets officers submit until the limit is reached, then blocks them', function () {
    limitTestSemesters();
    $form = limitTestForm(1);
    $officer = limitTestOfficer('a');

    $this->actingAs($officer)->get(route('forms.render', 'limited-form'))
        ->assertOk()
        ->assertSee('1 of 1 accepted submission remaining this semester.');

    $this->post(route('forms.render.submit', 'limited-form'), ['note' => 'first'])
        ->assertRedirect(route('forms.render', 'limited-form'));
    expect(FormSubmission::where('form_id', $form->id)->count())->toBe(1);

    // Another org's approved submission uses up the global quota.
    limitTestRequest($form, Carbon::now(), true);

    $this->get(route('forms.render', 'limited-form'))
        ->assertOk()
        ->assertSee('Submissions closed for this semester')
        ->assertDontSee('name="note"', false);

    $this->post(route('forms.render.submit', 'limited-form'), ['note' => 'second'])
        ->assertSessionHasErrors('form');
    expect(FormSubmission::where('form_id', $form->id)->count())->toBe(1);
});

it('does not enforce the limit when no semester is configured', function () {
    $form = limitTestForm(1);
    limitTestRequest($form, Carbon::now(), true);

    expect(\App\Forms\SemesterSubmissionLimit::status($form))->toBeNull();

    $this->actingAs(limitTestOfficer('b'))
        ->post(route('forms.render.submit', 'limited-form'), ['note' => 'ok'])
        ->assertSessionHasNoErrors();
});

it('warns the admin about limiting a system function form in Step 3', function () {
    $admin = limitTestUser(2, 'admin2');
    $systemForm = limitTestForm(null, \App\Forms\SystemFunction::NEW_EVENT);

    $this->actingAs($admin)->get(route('admin.form-builder.edit', $systemForm))
        ->assertOk()
        ->assertSee('Limit accepted submissions per semester')
        ->assertSee('Warning: this form drives a system function.');
});
