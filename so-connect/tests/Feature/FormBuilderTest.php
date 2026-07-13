<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeUser(int $type): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines',
        'province' => 'Tarlac',
        'town' => 'Camiling',
        'barangay' => 'Poblacion',
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Form',
        'last_name' => 'Builder',
        'middle_name' => 'T',
        'occupation' => 'Staff',
        'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => 'builder'.$type.'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

/**
 * Rendered form pages are gated to user-type-3 accounts holding an
 * officer/president role — the actor most tests need.
 */
function makeOfficerUser(?User $user = null): User
{
    $user = $user ?? makeUser(3);

    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Builder Org '.$user->getKey(),
        'detail_text' => 'Test organization',
        'initials' => 'BO',
    ]);
    $orgId = DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => $detailId,
    ]);
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

it('lets an admin open the builder pages', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->get(route('admin.form-builder.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.form-builder.create'))->assertOk();
});

it('blocks non-admins from the builder', function () {
    $member = makeUser(3);

    $this->actingAs($member)->get(route('admin.form-builder.index'))->assertForbidden();
});

it('stores a form with layout and fields', function () {
    $admin = makeUser(2);

    $payload = [
        'name' => 'Test Built Form',
        'description_text' => 'Collects member details for recognition.',
        'route_name' => 'test-built-form',
        'fields' => [
            ['field_key' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'is_required' => true, 'field_options' => []],
            ['field_key' => 'age', 'field_label' => 'Age', 'field_type' => 'age', 'is_required' => true, 'field_options' => ['min' => 1, 'max' => 99]],
            ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature', 'is_required' => false, 'field_options' => []],
        ],
        'rows' => [
            ['columns' => [['span' => 6, 'fields' => ['full_name']], ['span' => 6, 'fields' => ['age']]]],
            ['columns' => [['span' => 12, 'fields' => ['sig']]]],
        ],
        'pdf_template' => [
            'html' => '<p>Name: <span class="field-token" data-field="full_name" contenteditable="false">Full Name</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
            // The letterhead now lives on the printed template, not the form layout.
            'header' => ['title' => 'Hello', 'align' => 'center'],
            'footer' => [],
        ],
    ];

    $this->actingAs($admin)
        ->postJson(route('admin.form-builder.store'), $payload)
        ->assertOk()
        ->assertJsonStructure(['message', 'redirect']);

    $form = Form::where('route_name', 'test-built-form')->first();
    expect($form)->not->toBeNull();
    expect($form->fields()->count())->toBe(3);
    expect($form->pdf_template['header']['title'])->toBe('Hello');
    expect(count($form->layout['rows']))->toBe(2);
    expect($form->description_text)->toBe('Collects member details for recognition.');
    expect($form->pdf_template['html'])->toContain('data-field="full_name"');
    // Saving publishes — there is no draft checkbox anymore.
    expect($form->is_published)->toBeTrue();
    expect($form->is_active)->toBeTrue();
});

it('saves a template-less form as unpublished until the template exists', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'No Template', 'route_name' => 'no-template',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $form = Form::where('route_name', 'no-template')->first();
    expect($form)->not->toBeNull();
    expect($form->is_published)->toBeFalse();

    // Adding the printed template publishes on the next save.
    $this->actingAs($admin)->putJson(route('admin.form-builder.update', $form), [
        'name' => 'No Template', 'route_name' => 'no-template',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="a" contenteditable="false">A</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ])->assertOk();

    expect($form->fresh()->is_published)->toBeTrue();
});

it('rejects duplicate field keys', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Dup', 'route_name' => 'dup-form',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'a', 'field_label' => 'A2', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [],
    ])->assertStatus(422);
});

it('renders a published builder form and generates a PDF on submit', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $officer = makeOfficerUser();

    $form = Form::create([
        'name' => 'Renderable Form',
        'route_name' => 'renderable-form',
        'is_active' => true,
        'is_published' => true,
        'layout' => [
            'header' => ['title' => 'Renderable', 'align' => 'center'],
            'rows' => [
                ['columns' => [['span' => 6, 'fields' => ['full_name']], ['span' => 6, 'fields' => ['favorite']]]],
                ['columns' => [['span' => 12, 'fields' => ['photo']]]],
            ],
        ],
        'pdf_template' => [
            'html' => '<h1>Renderable</h1><p>Name: <span class="field-token" data-field="full_name" contenteditable="false">Full Name</span></p>'
                .'<p>Favorite: <span class="field-token" data-field="favorite" contenteditable="false">Favorite</span></p>'
                .'<p>Photo: <span class="field-token" data-field="photo" contenteditable="false">Photo</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    foreach ([
        ['field_key' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
        ['field_key' => 'favorite', 'field_label' => 'Favorite', 'field_type' => 'select', 'is_required' => true, 'field_order' => 2, 'field_options' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]],
        ['field_key' => 'photo', 'field_label' => 'Photo', 'field_type' => 'image', 'is_required' => false, 'field_order' => 3],
    ] as $f) {
        FormDescription::create($f + ['form_id' => $form->id]);
    }

    $this->actingAs($officer)
        ->get(route('forms.render', 'renderable-form'))
        ->assertOk()
        ->assertSee('Full Name')
        ->assertSee('Favorite');

    $response = $this->actingAs($officer)->post(route('forms.render.submit', 'renderable-form'), [
        'full_name' => 'Juan Dela Cruz',
        'favorite' => 'b',
        'photo' => UploadedFile::fake()->image('id.jpg', 100, 100),
    ]);

    $response->assertRedirect(route('documents.index'));

    $submission = FormSubmission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->payload['full_name'])->toBe('Juan Dela Cruz');
    expect($submission->payload['favorite'])->toBe('b');
    expect($submission->payload['photo'])->not->toBeNull();

    $generated = GeneratedDocument::where('form_submission_id', $submission->getKey())->first();
    expect($generated)->not->toBeNull();
    expect($generated->status)->toBe('generated');
    Storage::disk('public')->assertExists($generated->pdf_path);
});

it('enforces required-field validation on submit', function () {
    $officer = makeOfficerUser();

    $form = Form::create([
        'name' => 'Required Form', 'route_name' => 'required-form',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="needed" contenteditable="false">Needed</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'needed', 'field_label' => 'Needed',
        'field_type' => 'text', 'is_required' => true, 'field_order' => 1,
    ]);

    $this->actingAs($officer)
        ->post(route('forms.render.submit', 'required-form'), [])
        ->assertSessionHasErrors('needed');
});

it('gates rendered forms to officers and presidents only', function () {
    $form = Form::create([
        'name' => 'Gated Form', 'route_name' => 'gated-form',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="x" contenteditable="false">X</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'x', 'field_label' => 'X',
        'field_type' => 'text', 'field_order' => 1,
    ]);

    // Admins author forms; they don't get the rendered pages.
    $admin = makeUser(2);
    $this->actingAs($admin)->get(route('forms.render', 'gated-form'))->assertForbidden();
    $this->actingAs($admin)->post(route('forms.render.submit', 'gated-form'), ['x' => 'v'])->assertForbidden();

    // A type-3 user with no officer/president role is out too.
    $member = makeUser(3);
    $this->actingAs($member)->get(route('forms.render', 'gated-form'))->assertForbidden();

    // An officer gets through.
    $this->actingAs(makeOfficerUser($member))->get(route('forms.render', 'gated-form'))->assertOk();
});

it('persists and reloads a field universal_key mapping', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Mapped Form', 'route_name' => 'mapped-form',
        'fields' => [
            ['field_key' => 'fn', 'field_label' => 'First', 'field_type' => 'text', 'universal_key' => 'first_name'],
            ['field_key' => 'note', 'field_label' => 'Note', 'field_type' => 'text'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $form = Form::where('route_name', 'mapped-form')->first();
    expect($form->fields()->where('field_key', 'fn')->value('universal_key'))->toBe('first_name');
    expect($form->fields()->where('field_key', 'note')->value('universal_key'))->toBeNull();

    // Reload path: editorDataFromForm surfaces the mapping back into the editor.
    $response = $this->actingAs($admin)->get(route('admin.form-builder.edit', $form))->assertOk();
    $fields = collect($response->viewData('editorData')['fields']);
    expect($fields->firstWhere('field_key', 'fn')['universal_key'])->toBe('first_name');
    expect($fields->firstWhere('field_key', 'note')['universal_key'])->toBe('');
});

it('rejects an unknown universal_key', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Bad Map', 'route_name' => 'bad-map',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text', 'universal_key' => 'not_a_field'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422)->assertJsonValidationErrors('fields.0.universal_key');

    expect(Form::where('route_name', 'bad-map')->exists())->toBeFalse();
});

it('prefills mapped fields from the signed-in user profile', function () {
    // makeUser's profile has first_name = 'Form', last_name = 'Builder'.
    $user = makeOfficerUser();

    $form = Form::create([
        'name' => 'Prefill Form', 'route_name' => 'prefill-form',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span data-field="fn" contenteditable="false">First</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'fn', 'field_label' => 'First',
        'field_type' => 'text', 'field_order' => 1, 'universal_key' => 'first_name',
    ]);
    // Unmapped field: must NOT be pre-filled even though the profile has a value.
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'note', 'field_label' => 'Note',
        'field_type' => 'text', 'field_order' => 2,
    ]);

    $html = $this->actingAs($user)->get(route('forms.render', 'prefill-form'))
        ->assertOk()->getContent();

    expect($html)->toContain('value="Form"')      // mapped field autofilled
        ->not->toContain('value="Builder"');      // last_name is not mapped anywhere
});

it('does not prefill when the user has no profile', function () {
    $user = makeOfficerUser(User::query()->create([
        'user_email' => 'noprofile@example.com',
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => null,
    ]));

    $form = Form::create([
        'name' => 'No Profile Form', 'route_name' => 'no-profile-form',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span data-field="fn" contenteditable="false">First</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'fn', 'field_label' => 'First',
        'field_type' => 'text', 'field_order' => 1, 'universal_key' => 'first_name',
    ]);

    $html = $this->actingAs($user)->get(route('forms.render', 'no-profile-form'))
        ->assertOk()->getContent();

    expect($html)->toContain('name="fn"')->not->toContain('value="Form"');
});
