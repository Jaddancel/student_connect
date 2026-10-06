<?php

use App\Forms\OptionSource;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\ScoringCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function osUser(int $type = 3): User
{
    $profile = Profile::query()->create([
        'first_name' => 'Src'.Str::random(4),
        'last_name' => 'Person',
        'middle_name' => 'T',
        'occupation' => 'Staff',
    ]);

    return User::query()->create([
        'user_email' => 'src'.Str::random(8).'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

function osOrg(string $name): int
{
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => $name,
        'detail_text' => $name,
        'initials' => 'OS',
    ]);

    return (int) DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => $detailId,
    ]);
}

function osAssignOfficer(User $user, int $orgId): void
{
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

function osApprovedEvent(int $orgId, int $creatorId, string $title, string $status = 'approved'): int
{
    return (int) DB::table('event_plans')->insertGetId([
        'organization_id' => $orgId,
        'created_by' => $creatorId,
        'title' => $title,
        'target_date' => '2026-05-01',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('resolves organizations scoped to the officer and unscoped for admins', function () {
    $officer = osUser(3);
    $orgA = osOrg('Alpha Org');
    $orgB = osOrg('Beta Org');
    osAssignOfficer($officer, $orgA);

    // Scoped: the officer only sees their own organization.
    $scoped = OptionSource::values(OptionSource::ORGANIZATIONS, $officer, scoped: true);
    expect($scoped)->toContain((string) $orgA)->not->toContain((string) $orgB);

    // Unscoped (admin/tally): every organization is visible.
    $all = OptionSource::values(OptionSource::ORGANIZATIONS, $officer, scoped: false);
    expect($all)->toContain((string) $orgA)->toContain((string) $orgB);
});

it('scopes approved events to the officer organizations and hides unapproved ones', function () {
    $officer = osUser(3);
    $orgA = osOrg('Alpha Org');
    $orgB = osOrg('Beta Org');
    osAssignOfficer($officer, $orgA);

    $approvedA = osApprovedEvent($orgA, (int) $officer->getKey(), 'Alpha Seminar');
    $pendingA = osApprovedEvent($orgA, (int) $officer->getKey(), 'Alpha Draft', 'pending');
    $approvedB = osApprovedEvent($orgB, (int) $officer->getKey(), 'Beta Seminar');

    $scoped = OptionSource::values(OptionSource::APPROVED_EVENTS, $officer, scoped: true);
    expect($scoped)->toContain((string) $approvedA)
        ->not->toContain((string) $pendingA)   // only approved plans
        ->not->toContain((string) $approvedB); // only the officer's org

    // Unscoped sees every approved plan across organizations.
    $all = OptionSource::values(OptionSource::APPROVED_EVENTS, $officer, scoped: false);
    expect($all)->toContain((string) $approvedA)->toContain((string) $approvedB)
        ->not->toContain((string) $pendingA);
});

it('scopes the officers/members roster to the acting officer organizations', function () {
    $officer = osUser(3);
    $other = osUser(3);
    $orgA = osOrg('Alpha Org');
    $orgB = osOrg('Beta Org');
    osAssignOfficer($officer, $orgA);
    osAssignOfficer($other, $orgB);

    $scoped = OptionSource::values(OptionSource::OFFICERS, $officer, scoped: true);
    expect($scoped)->toContain((string) $officer->getKey())
        ->not->toContain((string) $other->getKey());

    $all = OptionSource::values(OptionSource::OFFICERS, $officer, scoped: false);
    expect($all)->toContain((string) $officer->getKey())->toContain((string) $other->getKey());
});

it('persists and reloads a field option source through the builder', function () {
    $admin = osUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Sourced Form',
        'route_name' => 'sourced-form',
        'fields' => [
            ['field_key' => 'org_pick', 'field_label' => 'Organization', 'field_type' => 'select',
                'field_options' => ['source' => 'organizations']],
            ['field_key' => 'member_pick', 'field_label' => 'Member', 'field_type' => 'search',
                'field_options' => ['source' => 'officers']],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $form = Form::where('route_name', 'sourced-form')->firstOrFail();
    expect($form->fields()->where('field_key', 'org_pick')->value('field_options')['source'])->toBe('organizations')
        ->and($form->fields()->where('field_key', 'member_pick')->value('field_options')['source'])->toBe('officers');

    // Reload surfaces the source back into the editor payload.
    $response = $this->actingAs($admin)->get(route('admin.form-builder.edit', $form))->assertOk();
    $fields = collect($response->viewData('editorData')['fields']);
    expect($fields->firstWhere('field_key', 'org_pick')['field_options']['source'])->toBe('organizations');
});

it('rejects an unknown option source on save', function () {
    $admin = osUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Bad Source', 'route_name' => 'bad-source',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'select',
                'field_options' => ['source' => 'secret_table']],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422)->assertJsonValidationErrors('fields.0.field_options.source');

    expect(Form::where('route_name', 'bad-source')->exists())->toBeFalse();
});

it('rejects a submitted value outside the scoped source and accepts one inside', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $officer = osUser(3);
    $orgA = osOrg('Alpha Org');
    $orgB = osOrg('Beta Org');
    osAssignOfficer($officer, $orgA);

    $form = Form::create([
        'name' => 'Pick Org', 'route_name' => 'pick-org',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="org_pick" contenteditable="false">Org</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'org_pick', 'field_label' => 'Organization',
        'field_type' => 'select', 'is_required' => true, 'field_order' => 1,
        'field_options' => ['source' => 'organizations'],
    ]);

    // Beta belongs to another officer — not in this submitter's scoped set.
    $this->actingAs($officer)
        ->post(route('forms.render.submit', 'pick-org'), ['org_pick' => (string) $orgB])
        ->assertSessionHasErrors('org_pick');

    // Alpha is authorized — it passes validation and a submission is recorded.
    $this->actingAs($officer)
        ->post(route('forms.render.submit', 'pick-org'), ['org_pick' => (string) $orgA])
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::where('form_id', $form->id)->latest('form_submission_id')->first();
    expect($submission)->not->toBeNull()
        ->and($submission->payload['org_pick'])->toBe((string) $orgA);
});

it('feeds sourced entries into the tally editor variable payload', function () {
    $admin = osUser(2);
    $orgA = osOrg('Alpha Org');
    $orgB = osOrg('Beta Org');

    $form = Form::create([
        'name' => 'Tally Sourced', 'route_name' => 'tally-sourced',
        'is_active' => true, 'is_published' => true, 'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'org_pick', 'field_label' => 'Organization',
        'field_type' => 'select', 'is_required' => true, 'field_order' => 1,
        'field_options' => ['source' => 'organizations'],
    ]);

    $criterion = ScoringCriterion::query()->create([
        'key' => 'custom_src_'.Str::random(5), 'category_key' => 'engagement',
        'label' => 'Sourced', 'weight' => 1, 'sort_order' => 1000, 'is_system' => false, 'is_active' => true,
    ]);

    $variables = $this->actingAs($admin)
        ->get(route('admin.scoring.rules.edit', $criterion))
        ->assertOk()
        ->viewData('variables');

    $formVars = collect($variables['forms'])->firstWhere('id', (int) $form->id);
    $field = collect($formVars['fields'])->firstWhere('key', 'org_pick');

    // Admin/unscoped: every organization is offered as a value option.
    $values = collect($field['options'])->pluck('value')->all();
    expect($values)->toContain((string) $orgA)->toContain((string) $orgB);
});

it('renders a sourced select and a search field with their scoped entries', function () {
    $officer = osUser(3);
    $orgA = osOrg('Alpha Rendered Org');
    osAssignOfficer($officer, $orgA);

    $form = Form::create([
        'name' => 'Rendered Sourced', 'route_name' => 'rendered-sourced',
        'is_active' => true, 'is_published' => true, 'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="org_pick" contenteditable="false">Org</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'org_pick', 'field_label' => 'Organization',
        'field_type' => 'select', 'is_required' => true, 'field_order' => 1,
        'field_options' => ['source' => 'organizations'],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'member_pick', 'field_label' => 'Member',
        'field_type' => 'search', 'is_required' => false, 'field_order' => 2,
        'field_options' => ['source' => 'officers'],
    ]);

    $html = $this->actingAs($officer)->get(route('forms.render', 'rendered-sourced'))
        ->assertOk()->getContent();

    expect($html)->toContain('Alpha Rendered Org')  // sourced <option>
        ->toContain('searchSelectField');            // combobox mounted
});
