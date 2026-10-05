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

it('does not require extension services on the new event form', function () {
    app(\Database\Seeders\FormPagesSeeder::class)->run();

    $form = Form::where('route_name', 'new-event')->firstOrFail();
    $field = $form->fields()->where('field_key', 'extension_services')->firstOrFail();

    expect((bool) $field->is_required)->toBeFalse();
});

it('blocks non-admins from the builder', function () {
    $member = makeUser(3);

    $this->actingAs($member)->get(route('admin.form-builder.index'))->assertForbidden();

    // Form authoring is Admin-only (user_type 2); super admins are out too.
    $superAdmin = makeUser(1);

    $this->actingAs($superAdmin)->get(route('admin.form-builder.index'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('admin.form-builder.create'))->assertForbidden();
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

it('publishes every printed-template draft slot and deactivates removed slots on edit', function () {
    Storage::fake('public');
    \Illuminate\Support\Facades\Queue::fake();
    $admin = makeUser(2);
    $templates = app(\App\Services\FormPrintTemplateService::class);
    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Multi printed', 'fields' => [
            ['field_key' => 'name', 'field_label' => 'Name', 'field_type' => 'text'],
        ],
    ])->assertOk()->json();
    $bytes = $templates->readDraftDocx($sync['draftId']);
    $this->post($sync['addUrl'], [
        'docx' => UploadedFile::fake()->createWithContent('extra.docx', $bytes),
    ], ['Accept' => 'application/json'])->assertOk();
    $payload = [
        'name' => 'Multi printed',
        'route_name' => 'multi-printed',
        'fields' => [['field_key' => 'name', 'field_label' => 'Name', 'field_type' => 'text']],
        'rows' => [],
        'draft_id' => $sync['draftId'],
    ];
    $this->postJson(route('admin.form-builder.store'), $payload)->assertOk();
    $form = Form::where('route_name', 'multi-printed')->firstOrFail();
    expect($templates->activeTemplates($form)->pluck('template_name')->all())
        ->toBe(['Multi printed', 'extra.docx']);

    $resync = $this->postJson(route('admin.form-builder.draft.sync'), [
        'form_id' => $form->getKey(), 'name' => 'Multi printed', 'fields' => [
            ['field_key' => 'name', 'field_label' => 'Name', 'field_type' => 'text'],
        ],
    ])->assertOk()->json();
    $this->deleteJson($resync['slots'][1]['removeUrl'])->assertOk();
    $payload['draft_id'] = $resync['draftId'];
    $this->putJson(route('admin.form-builder.update', $form), $payload)->assertOk();
    expect($templates->activeTemplates($form)->pluck('template_name')->all())->toBe(['Multi printed'])
        ->and($form->templates()->count())->toBe(2);
});

it('persists row header/static text and prunes fully-empty rows', function () {
    $admin = makeUser(2);

    $payload = [
        'name' => 'Row Props Form',
        'route_name' => 'row-props-form',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false, 'field_options' => []],
        ],
        'rows' => [
            // A field row carrying a header + static text.
            ['header' => 'Contact details', 'static_text' => "Line one\nLine two", 'columns' => [['span' => 12, 'fields' => ['a']]]],
            // A header-only "section divider" row (no fields) — must survive.
            ['header' => 'Standalone section', 'columns' => [['span' => 12, 'fields' => []]]],
            // A fully-empty row (no fields, no header/text) — must be dropped.
            ['columns' => [['span' => 12, 'fields' => []]]],
        ],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload)->assertOk();

    $rows = Form::where('route_name', 'row-props-form')->first()->layout['rows'];

    // The empty row is gone; the field row and the header-only divider remain.
    expect($rows)->toHaveCount(2);
    expect($rows[0]['header'])->toBe('Contact details');
    expect($rows[0]['static_text'])->toBe("Line one\nLine two");
    expect($rows[0]['columns'][0]['fields'])->toBe(['a']);
    expect($rows[1]['header'])->toBe('Standalone section');
    // Absent static text isn't stored as an empty string.
    expect($rows[1])->not->toHaveKey('static_text');
});

it('drops "Section" and "Static text" from the builder palette', function () {
    $palette = \App\Forms\FieldType::paletteCatalog();

    expect($palette)->not->toHaveKey(\App\Forms\FieldType::HEADING);
    expect($palette)->not->toHaveKey(\App\Forms\FieldType::STATIC_TEXT);
    // Still valid for rendering/validation of existing forms.
    expect(\App\Forms\FieldType::all())->toContain(\App\Forms\FieldType::HEADING);
    expect(\App\Forms\FieldType::all())->toContain(\App\Forms\FieldType::STATIC_TEXT);
});

it('publishes a form on save even without a printed template', function () {
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
    expect($form->is_published)->toBeTrue();

    // Stays published after a follow-up save that adds the printed template.
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

    // The lifecycle: submit → pending request; no document until approval.
    $response->assertRedirect(route('forms.render', 'renderable-form'));

    $submission = FormSubmission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->payload['full_name'])->toBe('Juan Dela Cruz');
    expect($submission->payload['favorite'])->toBe('b');
    expect($submission->payload['photo'])->not->toBeNull();
    // The submission is scoped to the submitter's organization.
    $officerOrgId = (int) DB::table('organization_officers')->where('user', $officer->getKey())->value('organization');
    expect((int) $submission->organization_id)->toBe($officerOrgId);

    $actionRequest = \App\Models\Request::query()
        ->where('form_id', $form->id)
        ->where('action_type', \App\Helpers\FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
        ->first();
    expect($actionRequest)->not->toBeNull()
        ->and((int) ($actionRequest->payload['submission_id'] ?? 0))->toBe((int) $submission->getKey())
        ->and((int) $actionRequest->organization_id)->toBe($officerOrgId);
    expect(GeneratedDocument::where('form_submission_id', $submission->getKey())->exists())->toBeFalse();

    // Admin approval generates the document end-to-end.
    $admin = makeUser(2);
    app(\App\Services\RequestApprovalService::class)->approve($actionRequest, (int) $admin->getKey());

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

it('boots empty field_options as a JSON object so options added in the editor survive save', function () {
    // Regression: a field with NULL field_options used to boot as `[]` (a JS
    // array); assigning `.options` to it set a named property that
    // JSON.stringify silently dropped, so options added in Step 1 never saved.
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Empty Options', 'route_name' => 'empty-options',
        'fields' => [
            ['field_key' => 'sponsor', 'field_label' => 'Sponsor', 'field_type' => 'select'],
        ],
        'rows' => [['columns' => [['span' => 12, 'fields' => ['sponsor']]]]],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $form = Form::where('route_name', 'empty-options')->first();
    expect($form->fields()->where('field_key', 'sponsor')->value('field_options'))->toBeNull();

    $response = $this->actingAs($admin)->get(route('admin.form-builder.edit', $form))->assertOk();
    $options = collect($response->viewData('editorData')['fields'])->firstWhere('field_key', 'sponsor')['field_options'];

    // Must serialize as `{}`, not `[]`, and round-trip back as an assoc array.
    expect(json_encode($options))->toBe('{}')
        ->and(json_decode(json_encode($options), true))->toBe([]);
});

it('seeds the printed-template draft from the form’s existing template', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $admin = makeUser(2);

    // A form whose template already exists (legacy HTML migrated on resolve).
    $form = Form::query()->create([
        'name' => 'Seeded Template',
        'route_name' => 'seeded-template-'.\Illuminate\Support\Str::random(8),
        'pdf_template' => ['html' => '<p>Hello <span data-field="full_name">Full name</span></p>'],
    ]);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'form_id' => $form->id,
        'name' => 'Seeded Template',
        'fields' => [
            ['field_key' => 'full_name', 'field_label' => 'Full name', 'field_type' => 'text'],
        ],
    ])->assertOk();

    $bytes = app(\App\Services\FormPrintTemplateService::class)->readDraftDocx($sync->json('draftId'));
    expect($bytes)->not->toBeNull();

    // The draft must open on the form's existing template, not the blank
    // starter — otherwise re-entering Step 2 after a save shows an empty
    // document and the next save wipes the layout.
    $tmp = tempnam(sys_get_temp_dir(), 'draft-').'.docx';
    file_put_contents($tmp, $bytes);
    expect(docxText($tmp))->toContain('Hello')->toContain('{{full_name}}');
    unlink($tmp);
});

it('starts the printed-template draft blank only for a brand-new form', function () {
    $admin = makeUser(2);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Blank Starter',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text'],
        ],
    ])->assertOk();

    $bytes = app(\App\Services\FormPrintTemplateService::class)->readDraftDocx($sync->json('draftId'));
    $tmp = tempnam(sys_get_temp_dir(), 'draft-').'.docx';
    file_put_contents($tmp, (string) $bytes);
    expect(docxText($tmp))->toContain('Blank Starter');
    unlink($tmp);
});

it('exposes the printed-template draft version for the save flush', function () {
    $admin = makeUser(2);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Flush Form',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text'],
        ],
    ])->assertOk();

    $draftId = $sync->json('draftId');

    $this->actingAs($admin)
        ->getJson(route('admin.form-builder.draft.version', $draftId))
        ->assertOk()
        ->assertJson(['version' => 1, 'closed' => false]);

    $this->actingAs($admin)
        ->getJson(route('admin.form-builder.draft.version', 'missing-draft'))
        ->assertNotFound();
});

it('marks the draft closed when the editor session ends without changes', function () {
    config(['onlyoffice.jwt_secret' => str_repeat('t', 40)]);
    $admin = makeUser(2);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Close Without Edits',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text'],
        ],
    ])->assertOk();
    $draftId = $sync->json('draftId');

    // Status 4 = session closed, nothing to save: no new revision, but the
    // save-flush poll must stop waiting — this is the flag that lets it.
    $token = app(\App\Services\OnlyOfficeService::class)->sign([
        'did' => $draftId, 'purpose' => 'callback', 'exp' => now()->addMinutes(30)->getTimestamp(),
    ]);
    $body = app(\App\Services\OnlyOfficeService::class)->sign(['status' => 4]);

    $this->postJson(route('onlyoffice.draft.callback', $draftId).'?token='.$token, ['token' => $body])
        ->assertOk()->assertJson(['error' => 0]);

    $this->actingAs($admin)
        ->getJson(route('admin.form-builder.draft.version', $draftId))
        ->assertOk()
        ->assertJson(['version' => 1, 'closed' => true]);
});

it('stores the revision and marks the draft closed on the final save callback', function () {
    config(['onlyoffice.jwt_secret' => str_repeat('t', 40)]);
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('EDITED-DOCX-BYTES')]);
    $admin = makeUser(2);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Final Save',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text'],
        ],
    ])->assertOk();
    $draftId = $sync->json('draftId');

    // Status 2 = ready to save: the version bump is what the save-flush polls
    // for, and the closed marker covers the nothing-to-save twin (status 4).
    $token = app(\App\Services\OnlyOfficeService::class)->sign([
        'did' => $draftId, 'purpose' => 'callback', 'exp' => now()->addMinutes(30)->getTimestamp(),
    ]);
    $body = app(\App\Services\OnlyOfficeService::class)->sign([
        'status' => 2, 'url' => 'http://onlyoffice/cache/files/output.docx',
    ]);

    $this->postJson(route('onlyoffice.draft.callback', $draftId).'?token='.$token, ['token' => $body])
        ->assertOk()->assertJson(['error' => 0]);

    $this->actingAs($admin)
        ->getJson(route('admin.form-builder.draft.version', $draftId))
        ->assertOk()
        ->assertJson(['version' => 2, 'closed' => true]);

    expect(app(\App\Services\FormPrintTemplateService::class)->readDraftDocx($draftId))
        ->toBe('EDITED-DOCX-BYTES');
});

it('does not mark the draft closed on a forcesave callback', function () {
    config(['onlyoffice.jwt_secret' => str_repeat('t', 40)]);
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('PARTIAL-BYTES')]);
    $admin = makeUser(2);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Forcesave',
        'fields' => [
            ['field_key' => 'x', 'field_label' => 'X', 'field_type' => 'text'],
        ],
    ])->assertOk();
    $draftId = $sync->json('draftId');

    // Status 6 = forcesave: content is stored but the session is still open,
    // so the closed marker must stay unset (more edits may still arrive).
    $token = app(\App\Services\OnlyOfficeService::class)->sign([
        'did' => $draftId, 'purpose' => 'callback', 'exp' => now()->addMinutes(30)->getTimestamp(),
    ]);
    $body = app(\App\Services\OnlyOfficeService::class)->sign([
        'status' => 6, 'url' => 'http://onlyoffice/cache/files/output.docx',
    ]);

    $this->postJson(route('onlyoffice.draft.callback', $draftId).'?token='.$token, ['token' => $body])
        ->assertOk()->assertJson(['error' => 0]);

    $this->actingAs($admin)
        ->getJson(route('admin.form-builder.draft.version', $draftId))
        ->assertOk()
        ->assertJson(['version' => 2, 'closed' => false]);
});

it('skips validation and drops the value of a conditionally hidden field', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $officer = makeOfficerUser();

    $form = Form::create([
        'name' => 'Conditional Form', 'route_name' => 'conditional-form',
        'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="kind" contenteditable="false">Kind</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'kind', 'field_label' => 'Kind',
        'field_type' => 'select', 'is_required' => true, 'field_order' => 1,
        'field_options' => ['options' => [['value' => 'standard', 'label' => 'Standard'], ['value' => 'other', 'label' => 'Other']]],
    ]);
    // Required — but only when kind = other.
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'other_details', 'field_label' => 'Other details',
        'field_type' => 'text', 'is_required' => true, 'field_order' => 2,
        'field_options' => ['visible_when' => ['field' => 'kind', 'op' => 'equals', 'value' => 'other']],
    ]);

    // Condition unmet: the required hidden field must not block, and any
    // smuggled value must be dropped from the payload.
    $this->actingAs($officer)->post(route('forms.render.submit', 'conditional-form'), [
        'kind' => 'standard',
        'other_details' => 'client-side tampering',
    ])->assertSessionHasNoErrors();

    $submission = FormSubmission::where('form_id', $form->id)->latest('form_submission_id')->first();
    expect($submission->payload['kind'])->toBe('standard')
        ->and($submission->payload['other_details'])->toBeNull();

    // Condition met: the field is required again.
    $this->actingAs($officer)->post(route('forms.render.submit', 'conditional-form'), [
        'kind' => 'other',
    ])->assertSessionHasErrors('other_details');
});

it('rejects self-referencing, circular and dangling visibility conditions', function () {
    $admin = makeUser(2);

    $base = fn (array $fields) => [
        'name' => 'Cond Rules', 'route_name' => 'cond-rules',
        'fields' => $fields, 'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];

    // Self-reference
    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $base([
        ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'a', 'op' => 'filled']]],
    ]))->assertStatus(422);

    // Cycle
    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $base([
        ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'b', 'op' => 'filled']]],
        ['field_key' => 'b', 'field_label' => 'B', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'a', 'op' => 'filled']]],
    ]))->assertStatus(422);

    // Dangling controller
    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $base([
        ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'ghost', 'op' => 'filled']]],
    ]))->assertStatus(422);

    // equals without a comparison value
    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $base([
        ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text'],
        ['field_key' => 'b', 'field_label' => 'B', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'a', 'op' => 'equals', 'value' => '']]],
    ]))->assertStatus(422);

    expect(Form::where('route_name', 'cond-rules')->exists())->toBeFalse();

    // A valid condition saves and persists.
    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $base([
        ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text'],
        ['field_key' => 'b', 'field_label' => 'B', 'field_type' => 'text',
            'field_options' => ['visible_when' => ['field' => 'a', 'op' => 'filled']]],
    ]))->assertOk();

    $form = Form::where('route_name', 'cond-rules')->firstOrFail();
    // toEqual (not toBe): MySQL normalises JSON object key order (by key length),
    // so the stored order is op/field/value — the content is what matters here.
    expect($form->fields()->where('field_key', 'b')->first()->field_options['visible_when'])
        ->toEqual(['field' => 'a', 'op' => 'filled', 'value' => '']);
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

it('persists a chosen sidebar icon', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Iconed Form', 'route_name' => 'iconed-form',
        'icon' => 'task',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [['columns' => [['span' => 12, 'fields' => ['a']]]]],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    expect(Form::where('route_name', 'iconed-form')->value('icon'))->toBe('task');
});

it('rejects an icon outside the allow-list', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Bad Icon', 'route_name' => 'bad-icon',
        'icon' => 'skull-and-crossbones',
        'fields' => [
            ['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [['columns' => [['span' => 12, 'fields' => ['a']]]]],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422)->assertJsonValidationErrors('icon');

    expect(Form::where('route_name', 'bad-icon')->exists())->toBeFalse();
});

/** The bound New Events form the Activity-Table column picker reads from. */
function builderNewEventForm(): Form
{
    $form = Form::create([
        'name' => 'New Event', 'route_name' => 'new-event-src',
        'system_function' => 'new_event', 'is_active' => true,
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'title', 'field_label' => 'Activity Title',
        'field_type' => 'text', 'field_order' => 1,
    ]);

    return $form;
}

it('persists normalized activity-table columns on the workplan form', function () {
    $admin = makeUser(2);
    builderNewEventForm();

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Workplan', 'route_name' => 'wp-built', 'system_function' => 'new_workplan',
        'fields' => [
            ['field_key' => 'name', 'field_label' => 'Workplan Name', 'field_type' => 'text'],
            ['field_key' => 'workplan_events', 'field_label' => 'Events', 'field_type' => 'workplan-events'],
            ['field_key' => 'wp_activities', 'field_label' => 'Activities', 'field_type' => 'activity-table',
                'field_options' => ['columns' => [
                    // Live key: label + type refreshed from the New Events form.
                    ['key' => 'title', 'label' => 'STALE LABEL', 'type' => 'number'],
                    // Empty key: dropped by normalization.
                    ['key' => '', 'label' => 'Empty', 'type' => 'text'],
                    // Unknown key (not on the live form): snapshot kept as-is.
                    ['key' => 'custom', 'label' => 'Custom', 'type' => 'text'],
                    // Duplicate key: deduped (first occurrence wins).
                    ['key' => 'title', 'label' => 'Dup', 'type' => 'text'],
                ]]],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $columns = Form::where('route_name', 'wp-built')->first()
        ->fields()->where('field_key', 'wp_activities')->value('field_options')['columns'];

    expect($columns)->toEqual([
        ['key' => 'title', 'label' => 'Activity Title', 'type' => 'text'],
        ['key' => 'custom', 'label' => 'Custom', 'type' => 'text'],
    ]);
});

it('rejects an activity-table field on a non-workplan form', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Plain', 'route_name' => 'plain-at',
        'fields' => [
            ['field_key' => 'acts', 'field_label' => 'Acts', 'field_type' => 'activity-table', 'field_options' => ['columns' => []]],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422);

    expect(Form::where('route_name', 'plain-at')->exists())->toBeFalse();
});

it('rejects a visibility condition that depends on an activity-table field', function () {
    $admin = makeUser(2);
    builderNewEventForm();

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Workplan', 'route_name' => 'wp-cond', 'system_function' => 'new_workplan',
        'fields' => [
            ['field_key' => 'name', 'field_label' => 'Name', 'field_type' => 'text'],
            ['field_key' => 'workplan_events', 'field_label' => 'Events', 'field_type' => 'workplan-events'],
            ['field_key' => 'wp_activities', 'field_label' => 'Acts', 'field_type' => 'activity-table', 'field_options' => ['columns' => []]],
            ['field_key' => 'note', 'field_label' => 'Note', 'field_type' => 'text',
                'field_options' => ['visible_when' => ['field' => 'wp_activities', 'op' => 'filled']]],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422);
});

it('exposes eventFieldChoices to the workplan editor page', function () {
    $admin = makeUser(2);
    builderNewEventForm();

    $form = Form::create([
        'name' => 'WP', 'route_name' => 'wp-edit', 'field_kit' => 'new_workplan', 'is_active' => true,
        'layout' => ['rows' => []],
    ]);

    $choices = $this->actingAs($admin)->get(route('admin.form-builder.edit', $form))
        ->assertOk()->viewData('eventFieldChoices');

    expect(collect($choices)->pluck('key'))->toContain('title');
});

it('saves the age-from-birthday autofill on the sign-up form', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Sign Up', 'route_name' => 'sign-up-age', 'system_function' => 'sign_up',
        'fields' => [
            ['field_key' => 'email', 'field_label' => 'E-mail', 'field_type' => 'new-officer-email'],
            ['field_key' => 'first_name', 'field_label' => 'First', 'field_type' => 'text'],
            ['field_key' => 'last_name', 'field_label' => 'Last', 'field_type' => 'text'],
            ['field_key' => 'organization_id', 'field_label' => 'Org', 'field_type' => 'org-select'],
            ['field_key' => 'position', 'field_label' => 'Position', 'field_type' => 'position-select'],
            ['field_key' => 'password', 'field_label' => 'Password', 'field_type' => 'password'],
            ['field_key' => 'birthday', 'field_label' => 'Birthday', 'field_type' => 'date', 'universal_key' => 'birthday'],
            ['field_key' => 'age', 'field_label' => 'Age', 'field_type' => 'number', 'universal_key' => 'age_from_birthday'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    expect(Form::where('route_name', 'sign-up-age')->first()
        ->fields()->where('field_key', 'age')->value('universal_key'))->toBe('age_from_birthday');
});

it('rejects the age-from-birthday autofill on a non-signup form', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Plain', 'route_name' => 'plain-age',
        'fields' => [
            ['field_key' => 'age', 'field_label' => 'Age', 'field_type' => 'number', 'universal_key' => 'age_from_birthday'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertStatus(422);

    expect(Form::where('route_name', 'plain-age')->exists())->toBeFalse();
});

it('stores a calculate-from date mapping on a row-bound number field', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Date Age', 'route_name' => 'date-age',
        'fields' => [
            ['field_key' => 'birthday', 'field_label' => 'Birthday', 'field_type' => 'date'],
            ['field_key' => 'age', 'field_label' => 'Age', 'field_type' => 'number', 'field_options' => ['calculate_from' => 'birthday']],
        ],
        'rows' => [
            ['columns' => [
                ['span' => 6, 'fields' => ['birthday']],
                ['span' => 6, 'fields' => ['age']],
            ]],
        ],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $form = Form::where('route_name', 'date-age')->first();
    expect($form->fields()->where('field_key', 'age')->first()->field_options)->toMatchArray([
        'calculate_from' => 'birthday',
    ]);
});

it('round-trips the multi-column layout shape', function () {
    $admin = makeUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Shape Form', 'route_name' => 'shape-form',
        'fields' => [
            ['field_key' => 'first', 'field_label' => 'First', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'second', 'field_label' => 'Second', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'third', 'field_label' => 'Third', 'field_type' => 'text', 'is_required' => false],
        ],
        'rows' => [
            ['columns' => [
                ['span' => 6, 'fields' => ['first']],
                ['span' => 6, 'fields' => ['second']],
            ]],
            ['columns' => [['span' => 12, 'fields' => ['third']]]],
        ],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $rows = Form::where('route_name', 'shape-form')->first()->layout['rows'];

    expect($rows)->toHaveCount(2);
    expect($rows[0]['columns'])->toHaveCount(2);
    expect($rows[0]['columns'][0])->toBe(['span' => 6, 'fields' => ['first']]);
    expect($rows[0]['columns'][1])->toBe(['span' => 6, 'fields' => ['second']]);
    expect($rows[1]['columns'])->toHaveCount(1);
    expect($rows[1]['columns'][0])->toBe(['span' => 12, 'fields' => ['third']]);
});

it('saves a table row-total operation and rejects unknown ones', function () {
    $admin = makeUser(2);
    $payload = fn (string $op) => [
        'name' => 'Budget', 'route_name' => 'budget-'.$op,
        'fields' => [[
            'field_key' => 'budget', 'field_label' => 'Budget', 'field_type' => 'table-input', 'is_required' => false,
            'field_options' => [
                'columns' => [
                    ['key' => 'income', 'label' => 'Income', 'type' => 'number', 'required' => false],
                    ['key' => 'expense', 'label' => 'Expense', 'type' => 'number', 'required' => false],
                ],
                'row_total' => ['key' => 'net', 'label' => 'Net', 'op' => $op, 'multiply' => ['income', 'expense']],
            ],
        ]],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload('subtract'))->assertOk();
    $options = Form::where('route_name', 'budget-subtract')->firstOrFail()->fields()->first()->field_options;
    expect($options['row_total'])->toEqual(['key' => 'net', 'label' => 'Net', 'op' => 'subtract', 'multiply' => ['income', 'expense']]);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload('power'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fields.0.field_options.row_total.op');
});

it('stores a calculate-from table mapping with its column', function () {
    $admin = makeUser(2);
    $payload = fn (string $route, string $column) => [
        'name' => 'Column Sum', 'route_name' => $route,
        'fields' => [
            ['field_key' => 'funds', 'field_label' => 'Funds', 'field_type' => 'table-input', 'field_options' => [
                'columns' => [['key' => 'cash', 'label' => 'Cash', 'type' => 'number', 'required' => false]],
            ]],
            ['field_key' => 'cash_total', 'field_label' => 'Cash total', 'field_type' => 'number',
                'field_options' => ['calculate_from' => 'funds', 'calculate_column' => $column]],
        ],
        'rows' => [['columns' => [['span' => 12, 'fields' => ['funds', 'cash_total']]]]],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload('column-sum', 'cash'))->assertOk();
    expect(Form::where('route_name', 'column-sum')->first()->fields()->where('field_key', 'cash_total')->first()->field_options)
        ->toMatchArray(['calculate_from' => 'funds', 'calculate_column' => 'cash']);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), $payload('column-sum-2', 'cash; drop'))
        ->assertUnprocessable()->assertJsonValidationErrors('fields.1.field_options.calculate_column');
});
