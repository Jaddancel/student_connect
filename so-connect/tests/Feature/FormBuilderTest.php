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
        'route_name' => 'test-built-form',
        'sidebar_group' => ['admin'],
        'is_active' => true,
        'is_published' => true,
        'header' => ['title' => 'Hello', 'align' => 'center'],
        'fields' => [
            ['field_key' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'is_required' => true, 'field_options' => []],
            ['field_key' => 'age', 'field_label' => 'Age', 'field_type' => 'age', 'is_required' => true, 'field_options' => ['min' => 1, 'max' => 99]],
            ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature', 'is_required' => false, 'field_options' => []],
        ],
        'rows' => [
            ['columns' => [['span' => 6, 'fields' => ['full_name']], ['span' => 6, 'fields' => ['age']]]],
            ['columns' => [['span' => 12, 'fields' => ['sig']]]],
        ],
    ];

    $this->actingAs($admin)
        ->postJson(route('admin.form-builder.store'), $payload)
        ->assertOk()
        ->assertJsonStructure(['message', 'redirect']);

    $form = Form::where('route_name', 'test-built-form')->first();
    expect($form)->not->toBeNull();
    expect($form->fields()->count())->toBe(3);
    expect($form->layout['header']['title'])->toBe('Hello');
    expect(count($form->layout['rows']))->toBe(2);
});

it('rejects duplicate field keys', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Dup', 'route_name' => 'dup-form',
        'is_active' => true, 'is_published' => false,
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

    $admin = makeUser(2);

    $form = Form::create([
        'name' => 'Renderable Form',
        'route_name' => 'renderable-form',
        'is_active' => true,
        'is_published' => true,
        'sidebar_group' => ['admin'],
        'layout' => [
            'header' => ['title' => 'Renderable', 'align' => 'center'],
            'rows' => [
                ['columns' => [['span' => 6, 'fields' => ['full_name']], ['span' => 6, 'fields' => ['favorite']]]],
                ['columns' => [['span' => 12, 'fields' => ['photo']]]],
            ],
        ],
    ]);

    foreach ([
        ['field_key' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
        ['field_key' => 'favorite', 'field_label' => 'Favorite', 'field_type' => 'select', 'is_required' => true, 'field_order' => 2, 'field_options' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]],
        ['field_key' => 'photo', 'field_label' => 'Photo', 'field_type' => 'image', 'is_required' => false, 'field_order' => 3],
    ] as $f) {
        FormDescription::create($f + ['form_id' => $form->id]);
    }

    $this->actingAs($admin)
        ->get(route('forms.render', 'renderable-form'))
        ->assertOk()
        ->assertSee('Full Name')
        ->assertSee('Favorite');

    $response = $this->actingAs($admin)->post(route('forms.render.submit', 'renderable-form'), [
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
    $admin = makeUser(2);

    $form = Form::create([
        'name' => 'Required Form', 'route_name' => 'required-form',
        'is_active' => true, 'is_published' => true, 'sidebar_group' => ['admin'],
        'layout' => ['rows' => []],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'needed', 'field_label' => 'Needed',
        'field_type' => 'text', 'is_required' => true, 'field_order' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('forms.render.submit', 'required-form'), [])
        ->assertSessionHasErrors('needed');
});
