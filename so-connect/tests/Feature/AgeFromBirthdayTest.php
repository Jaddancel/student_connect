<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** A type-3 officer who may render/submit built forms. */
function ageOfficer(): User
{
    $profile = Profile::query()->create([
        'first_name' => 'Aria', 'last_name' => 'Byrne', 'middle_name' => 'C', 'occupation' => 'Officer',
    ]);
    $user = User::query()->create([
        'user_email' => 'age'.\Illuminate\Support\Str::random(6).'@example.com',
        'user_password' => 'password', 'user_type' => 3, 'profile' => $profile->getKey(),
    ]);
    $detailId = DB::table('organization_details')->insertGetId(['name' => 'Age Org', 'detail_text' => 'o', 'initials' => 'AO']);
    $orgId = DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $orgId, 'user' => (int) $user->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    return $user;
}

/** A form with a birthday date field and an age field mapped to age_from_birthday. */
function ageForm(): Form
{
    $form = Form::query()->create([
        'name' => 'Age Form', 'route_name' => 'age-form', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'birthday', 'field_label' => 'Birthday',
        'field_type' => 'date', 'field_order' => 1, 'universal_key' => 'birthday',
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'age', 'field_label' => 'Age',
        'field_type' => 'number', 'field_order' => 2, 'universal_key' => 'age_from_birthday',
    ]);

    return $form;
}

beforeEach(function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    Carbon::setTestNow(Carbon::create(2030, 6, 15, 12));
});

afterEach(fn () => Carbon::setTestNow());

it('recomputes the age from the birthday, overriding the client value', function () {
    $officer = ageOfficer();
    $form = ageForm();

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'birthday' => '2000-06-15',
        'age' => '99', // tampered client value — must be ignored
    ])->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->latest('form_submission_id')->first();
    expect($submission->payload['age'])->toBe(30);
});

it('stores a null age when the birthday is missing', function () {
    $officer = ageOfficer();
    $form = ageForm();

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'birthday' => '',
        'age' => '50',
    ])->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->latest('form_submission_id')->first();
    expect($submission->payload['age'])->toBeNull();
});

it('stamps the universal-key markers on the rendered form', function () {
    $officer = ageOfficer();
    $form = ageForm();

    $html = $this->actingAs($officer)->get(route('forms.render', $form->route_name))->assertOk()->getContent();

    expect($html)->toContain('data-universal-key="birthday"')
        ->toContain('data-universal-key="age_from_birthday"');
});
