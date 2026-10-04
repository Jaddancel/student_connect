<?php

use App\Models\Form;
use App\Models\Template;
use App\Services\DocxConverter;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Pull the palette's baked-in token JSON out of the rendered plugin page. */
function paletteTokens(string $html): array
{
    preg_match('~<script id="token-data" type="application/json">(.*?)</script>~s', $html, $m);

    return json_decode($m[1] ?? '[]', true) ?: [];
}

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice.test:8081',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.app_url' => 'http://laravel.test',
        'onlyoffice.jwt_secret' => str_repeat('t', 40),
    ]);
});

/** A form carrying a legacy rich-text printed template. */
function editorForm(?string $legacyHtml = null): Form
{
    return Form::query()->create([
        'name' => 'Event Attendance',
        'route_name' => 'forms.event-attendance-'.\Illuminate\Support\Str::random(8),
        'pdf_template' => $legacyHtml === null ? null : ['html' => $legacyHtml],
    ]);
}

/** Mint a token the way the controller does. */
function editorToken(Template $template, string $purpose, int $minutes = 30): string
{
    return app(OnlyOfficeService::class)->sign([
        'tid' => (int) $template->getKey(),
        'purpose' => $purpose,
        'exp' => now()->addMinutes($minutes)->getTimestamp(),
    ]);
}

it('signs and verifies its own tokens', function () {
    $service = app(OnlyOfficeService::class);

    $claims = $service->verify($service->sign(['tid' => 7, 'purpose' => 'document']));

    expect($claims)->not->toBeNull()
        ->and($claims['tid'])->toBe(7);
});

it('rejects a token signed with a different secret', function () {
    $foreign = app(OnlyOfficeService::class)->sign(['tid' => 1]);

    config(['onlyoffice.jwt_secret' => str_repeat('x', 40)]);

    expect(app(OnlyOfficeService::class)->verify($foreign))->toBeNull();
});

it('changes the document key when the template changes', function () {
    $template = app(FormPrintTemplateService::class)->resolve(editorForm());
    $service = app(OnlyOfficeService::class);

    $before = $service->documentKey($template);
    app(FormPrintTemplateService::class)->storeRevision($template, 'new-bytes');

    // A stale key would leave everyone editing the previous revision.
    expect($service->documentKey($template->refresh()))->not->toBe($before);
});

it('uses OnlyOffice to render docx files to PDF with Step 2 fidelity', function () {
    config(['documents.converter.url' => '']);

    Http::fake([
        'http://onlyoffice/converter*' => Http::response([
            'endConvert' => true,
            'fileType' => 'pdf',
            'fileUrl' => 'http://onlyoffice/cache/files/rendered.pdf',
            'percent' => 100,
        ]),
        'http://onlyoffice/cache/files/rendered.pdf' => Http::response('%PDF-onlyoffice'),
    ]);

    $workDir = storage_path('app/tmp/converter-test-'.\Illuminate\Support\Str::random(8));
    \Illuminate\Support\Facades\File::ensureDirectoryExists($workDir);
    $input = $workDir.'/template.docx';
    \Illuminate\Support\Facades\File::put($input, 'docx-bytes');

    try {
        $output = app(DocxConverter::class)->convert($input, 'pdf', $workDir);

        expect($output)->not->toBeNull()
            ->and(\Illuminate\Support\Facades\File::get($output))->toBe('%PDF-onlyoffice');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://onlyoffice/converter')
            && $request['filetype'] === 'docx'
            && $request['outputtype'] === 'pdf'
            && is_string($request['token'] ?? null));
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($workDir);
    }
});

it('protects temporary OnlyOffice conversion sources with a scoped token', function () {
    $conversionId = 'conversion-source-test';
    \Illuminate\Support\Facades\Cache::store('file')->put(
        DocxConverter::SOURCE_CACHE_PREFIX.$conversionId,
        'private-docx-bytes',
        now()->addMinute(),
    );

    try {
        $this->get(route('onlyoffice.conversion-source', $conversionId))->assertForbidden();

        $token = app(OnlyOfficeService::class)->sign([
            'cid' => $conversionId,
            'purpose' => 'conversion-source',
            'exp' => now()->addMinute()->getTimestamp(),
        ]);

        $this->get(route('onlyoffice.conversion-source', $conversionId).'?token='.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertSee('private-docx-bytes', false);
    } finally {
        \Illuminate\Support\Facades\Cache::store('file')->forget(
            DocxConverter::SOURCE_CACHE_PREFIX.$conversionId,
        );
    }
});

it('falls back to the document sidecar when OnlyOffice conversion fails', function () {
    config(['documents.converter.url' => 'http://docxconvert:3000']);

    Http::fake([
        'http://onlyoffice/converter*' => Http::response([], 500),
        'http://docxconvert:3000/convert' => Http::response('%PDF-libreoffice'),
    ]);

    $workDir = storage_path('app/tmp/converter-fallback-'.\Illuminate\Support\Str::random(8));
    \Illuminate\Support\Facades\File::ensureDirectoryExists($workDir);
    $input = $workDir.'/template.docx';
    \Illuminate\Support\Facades\File::put($input, 'docx-bytes');

    try {
        $output = app(DocxConverter::class)->convert($input, 'pdf', $workDir);

        expect($output)->not->toBeNull()
            ->and(\Illuminate\Support\Facades\File::get($output))->toBe('%PDF-libreoffice');
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($workDir);
    }
});

it('migrates a legacy rich-text template into the .docx on first resolve', function () {
    $form = editorForm('<p>Hello <span data-field="full_name">Full name</span></p>');

    $template = app(FormPrintTemplateService::class)->resolve($form);

    expect($template->docx_path)->toContain('printed-template.docx')
        ->and(app(FormPrintTemplateService::class)->fileExists($template))->toBeTrue();

    // The token span must survive as a {{…}} placeholder, or generation later
    // prints nothing where the field should be.
    expect(docxText(app(FormPrintTemplateService::class)->absolutePath($template)))
        ->toContain('{{full_name}}');
});

it('seeds a blank document for a form with no legacy template', function () {
    $template = app(FormPrintTemplateService::class)->resolve(editorForm());

    expect(app(FormPrintTemplateService::class)->fileExists($template))->toBeTrue();
});

it('gives an admin a signed editor config', function () {
    $form = editorForm();

    $response = $this->actingAs(recordsUser(2))
        ->getJson(route('admin.form-builder.printed-template.config', $form))
        ->assertOk();

    $config = $response->json('config');

    expect($response->json('enabled'))->toBeTrue()
        // Fetched by the Document Server, so it must use the app's container host.
        ->and($config['document']['url'])->toStartWith('http://laravel.test/onlyoffice/')
        ->and($config['editorConfig']['callbackUrl'])->toStartWith('http://laravel.test/onlyoffice/')
        // Loaded by the browser, so it must use the public app URL. The endpoint
        // must literally end in "config.json": OnlyOffice derives the plugin's
        // baseUrl as url.substring(0, url.lastIndexOf("config.json")).
        ->and($config['editorConfig']['plugins']['pluginsData'][0])->toContain('/config.json?token=');

    // The signature has to cover the plugins block or the server rejects it.
    $claims = app(OnlyOfficeService::class)->verify($config['token']);
    expect($claims['editorConfig']['plugins']['autostart'][0])->toContain('asc.{');
});

it('reports the editor as unavailable when it is not configured', function () {
    config(['onlyoffice.public_url' => '']);

    $this->actingAs(recordsUser(2))
        ->getJson(route('admin.form-builder.printed-template.config', editorForm()))
        ->assertStatus(503)
        ->assertJson(['enabled' => false]);
});

it('keeps the editor config behind admin auth', function () {
    $this->actingAs(recordsUser(3))
        ->getJson(route('admin.form-builder.printed-template.config', editorForm()))
        ->assertForbidden();
});

it('serves the document to a caller holding a valid token', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);

    $this->get(route('onlyoffice.document', $form).'?token='.editorToken($template, 'document'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('refuses a document request with a bad, mis-scoped or cross-form token', function () {
    $form = editorForm();
    $other = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);

    $url = route('onlyoffice.document', $form);

    $this->get($url.'?token=not-a-token')->assertForbidden();
    // A token minted for the palette must not fetch the document.
    $this->get($url.'?token='.editorToken($template, 'plugin'))->assertForbidden();
    // Nor may one form's token reach another form's document.
    $this->get(route('onlyoffice.document', $other).'?token='.editorToken($template, 'document'))
        ->assertForbidden();
});

it('refuses an expired token', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);

    // Backdated rather than time-travelled: php-jwt validates against PHP's
    // own time(), which Laravel's travel() does not move.
    $expired = app(OnlyOfficeService::class)->sign([
        'tid' => (int) $template->getKey(),
        'purpose' => 'document',
        'exp' => time() - 60,
    ]);

    $this->get(route('onlyoffice.document', $form).'?token='.$expired)->assertForbidden();
});

it('saves a new revision when the server posts a signed save callback', function () {
    Http::fake(['*' => Http::response('EDITED-DOCX-BYTES')]);

    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);
    $versionBefore = $template->version;

    $body = app(OnlyOfficeService::class)->sign([
        'status' => 2,
        'url' => 'http://onlyoffice/cache/files/output.docx',
    ]);

    $this->postJson(
        route('onlyoffice.callback', $form).'?token='.editorToken($template, 'callback'),
        ['token' => $body],
    )->assertOk()->assertJson(['error' => 0]);

    $template->refresh();

    expect($template->version)->toBe($versionBefore + 1)
        ->and(Storage::disk('public')->get($template->docx_path))->toBe('EDITED-DOCX-BYTES');
});

it('fetches the edited document through the internal URL when the callback names the public one', function () {
    Http::fake(['*' => Http::response('EDITED-DOCX-BYTES')]);

    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);

    // The Document Server builds cache URLs on its PUBLIC address (the one in
    // the browser's editor config). From the app container that host is
    // unreachable, so the controller must fetch via ONLYOFFICE_INTERNAL_URL —
    // the signed path+query authorise the download on either address.
    $body = app(OnlyOfficeService::class)->sign([
        'status' => 2,
        'url' => 'http://onlyoffice.test:8081/cache/files/data/abc/output.docx?md5=x&expires=9',
    ]);

    $this->postJson(
        route('onlyoffice.callback', $form).'?token='.editorToken($template, 'callback'),
        ['token' => $body],
    )->assertOk()->assertJson(['error' => 0]);

    Http::assertSent(fn ($request) => $request->url()
        === 'http://onlyoffice/cache/files/data/abc/output.docx?md5=x&expires=9');

    expect(Storage::disk('public')->get($template->refresh()->docx_path))->toBe('EDITED-DOCX-BYTES');
});

it('rejects a save callback whose body is not signed', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);

    // An unsigned body on a JWT-enabled server is an attacker overwriting a
    // template, not a real save.
    $this->postJson(
        route('onlyoffice.callback', $form).'?token='.editorToken($template, 'callback'),
        ['status' => 2, 'url' => 'http://evil.test/payload.docx'],
    )->assertStatus(403);
});

it('acknowledges non-save callback statuses without touching the document', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);
    $versionBefore = $template->version;

    $body = app(OnlyOfficeService::class)->sign(['status' => 1]);

    $this->postJson(
        route('onlyoffice.callback', $form).'?token='.editorToken($template, 'callback'),
        ['token' => $body],
    )->assertOk()->assertJson(['error' => 0]);

    expect($template->refresh()->version)->toBe($versionBefore);
});

it('serves a per-form token palette listing that form\'s fields', function () {
    $form = editorForm();
    $form->fields()->create([
        'field_key' => 'attendee_name',
        'field_label' => 'Attendee name',
        'field_type' => 'text',
        'field_order' => 1,
    ]);
    // Headings carry no value, so they must not appear as insertable tokens.
    $form->fields()->create([
        'field_key' => 'section_title',
        'field_label' => 'Section',
        'field_type' => 'heading',
        'field_order' => 2,
    ]);

    $template = app(FormPrintTemplateService::class)->resolve($form);
    $token = editorToken($template, 'plugin');

    $config = $this->get(route('onlyoffice.plugin-config', $form).'?token='.$token)
        ->assertOk()
        ->assertJsonPath('guid', 'asc.{8B3D2A41-6C7E-4F55-9E2B-1A4C9D0E7F31}');

    // The variation URL must be RELATIVE (a "plugin/{token}" path). OnlyOffice
    // resolves it against the plugin baseUrl (…/onlyoffice/{form}/) it derived
    // from the config.json URL; an absolute URL gets doubled by the editor.
    expect($config->json('variations.0.url'))->toStartWith('plugin/')
        ->and($config->json('variations.0.url'))->not->toStartWith('http');

    // The editor fetches config.json cross-origin from the Document Server's
    // origin; without CORS the browser drops it and the plugin never registers.
    expect($config->headers->get('Access-Control-Allow-Origin'))->toBe('http://onlyoffice.test:8081');

    // plugins.js inside the iframe fetches "./config.json" (…/plugin/config.json)
    // to read the guid before it posts its init handshake — without this the
    // panel loads but never renders its fields.
    $handshake = $this->get(route('onlyoffice.plugin-handshake', $form))
        ->assertOk()
        ->assertJsonPath('guid', 'asc.{8B3D2A41-6C7E-4F55-9E2B-1A4C9D0E7F31}');
    expect($handshake->headers->get('Access-Control-Allow-Origin'))->toBe('http://onlyoffice.test:8081');

    $palette = $this->get(route('onlyoffice.plugin', ['form' => $form, 'token' => $token]))->assertOk();

    expect($palette->getContent())->toContain('attendee_name')
        ->not->toContain('section_title');

    // The Document Server frames this, so SAMEORIGIN must not survive here — and
    // frame-ancestors must allow BOTH the app's own origin ('self', the top-level
    // host page) and the Document Server origin (the editor iframe around it).
    expect($palette->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'self'")
        ->toContain('http://onlyoffice.test:8081');
    expect($palette->headers->get('X-Frame-Options'))->toBeNull();
    expect($palette->headers->get('Access-Control-Allow-Origin'))->toBe('http://onlyoffice.test:8081');
});

it('serves the plugin toolbar icon as an SVG coin', function () {
    $form = editorForm();

    // The editor resolves the config's icons [icon.png, icon@2x.png] against the
    // plugin baseUrl and loads them as images; both must resolve to the coin.
    foreach (['icon.png', 'icon@2x.png'] as $name) {
        $icon = $this->get(route('onlyoffice.plugin-icon', ['form' => $form, 'icon' => $name]))->assertOk();
        expect($icon->headers->get('Content-Type'))->toContain('image/svg+xml');
        // The coin: a disc (circle) with a star emboss (a path).
        expect($icon->getContent())->toContain('<svg')->toContain('<circle')->toContain('<path');
    }
});

it('tags palette tokens with a field-type icon and category group', function () {
    $form = editorForm();
    $form->fields()->create([
        'field_key' => 'attendee_name',
        'field_label' => 'Attendee name',
        'field_type' => 'text',
        'field_order' => 1,
    ]);
    $form->fields()->create([
        'field_key' => 'course',
        'field_label' => 'Course',
        'field_type' => 'select',
        'field_order' => 2,
    ]);

    $template = app(FormPrintTemplateService::class)->resolve($form);
    $token = editorToken($template, 'plugin');

    $html = $this->get(route('onlyoffice.plugin', ['form' => $form, 'token' => $token]))->assertOk()->getContent();
    $tokens = collect(paletteTokens($html))->keyBy('key');

    // Form fields carry their FieldType icon + the builder's category label.
    expect($tokens['attendee_name'])->toMatchArray(['icon' => 'text', 'group' => 'Basic'])
        ->and($tokens['course'])->toMatchArray(['icon' => 'select', 'group' => 'Choice']);

    // Universal tokens collapse into Profile / Organization sections.
    expect($tokens['profile.first_name']['group'])->toBe('Profile')
        ->and($tokens['profile.org_name']['group'])->toBe('Organization');
});

it('emits an activity-table token that inserts a table with column children', function () {
    $form = editorForm();
    $form->fields()->create([
        'field_key' => 'attendee_name', 'field_label' => 'Attendee', 'field_type' => 'text', 'field_order' => 1,
    ]);
    $form->fields()->create([
        'field_key' => 'wp_activities', 'field_label' => 'Activities', 'field_type' => 'activity-table', 'field_order' => 2,
        'field_options' => ['columns' => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'target_date', 'label' => 'Date', 'type' => 'date'],
        ]],
    ]);

    $template = app(FormPrintTemplateService::class)->resolve($form);
    $token = editorToken($template, 'plugin');

    $html = $this->get(route('onlyoffice.plugin', ['form' => $form, 'token' => $token]))->assertOk()->getContent();
    $tokens = collect(paletteTokens($html))->keyBy('key');

    // The Activity Table inserts a whole table, carrying its columns as children.
    expect($tokens['wp_activities']['insert'])->toBe('table')
        ->and(collect($tokens['wp_activities']['children'])->pluck('key')->all())
        ->toBe(['wp_activities.title', 'wp_activities.target_date']);

    // Plain fields carry neither marker.
    expect($tokens['attendee_name'])->not->toHaveKey('children')
        ->and($tokens['attendee_name'])->not->toHaveKey('insert');

    // The palette JS knows how to paste a real Word table.
    expect($html)->toContain('PasteHtml');
});

it('emits a table-input token that inserts a table with its columns and row total as children', function () {
    $form = editorForm();
    $form->fields()->create([
        'field_key' => 'org_name', 'field_label' => 'Organization', 'field_type' => 'text', 'field_order' => 1,
    ]);
    $form->fields()->create([
        'field_key' => 'expenses', 'field_label' => 'Expenses', 'field_type' => 'table-input', 'field_order' => 2,
        'field_options' => [
            'columns' => [
                ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'number'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ],
            'row_total' => ['key' => 'line_total', 'label' => 'Total', 'multiply' => ['price', 'qty']],
        ],
    ]);

    $template = app(FormPrintTemplateService::class)->resolve($form);
    $token = editorToken($template, 'plugin');

    $html = $this->get(route('onlyoffice.plugin', ['form' => $form, 'token' => $token]))->assertOk()->getContent();
    $tokens = collect(paletteTokens($html))->keyBy('key');

    // The Table field inserts a whole table, carrying its columns (plus the
    // per-row computed column) as children.
    expect($tokens['expenses']['insert'])->toBe('table')
        ->and(collect($tokens['expenses']['children'])->pluck('key')->all())
        ->toBe(['expenses.item', 'expenses.price', 'expenses.qty', 'expenses.line_total']);

    // Plain fields carry neither marker.
    expect($tokens['org_name'])->not->toHaveKey('children')
        ->and($tokens['org_name'])->not->toHaveKey('insert');

    expect($html)->toContain('PasteHtml');
});

it('replaces the printed template with an uploaded .docx', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);
    $versionBefore = $template->version;

    // A genuine .docx to upload: seed a second form's document and reuse its bytes.
    $other = app(FormPrintTemplateService::class)->resolve(editorForm());
    $docxBytes = Storage::disk('public')->get($other->docx_path);

    $this->actingAs(recordsUser(2))
        ->post(route('admin.form-builder.printed-template.import', $form), [
            'docx' => UploadedFile::fake()->createWithContent('replacement.docx', $docxBytes),
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $template->refresh();

    // A new revision (fresh document key) carrying the uploaded bytes.
    expect($template->version)->toBe($versionBefore + 1)
        ->and(Storage::disk('public')->get($template->docx_path))->toBe($docxBytes);
});

it('closes the draft editor before uploading a replacement document', function () {
    $source = file_get_contents(resource_path('views/components/form-builder/onlyoffice-template.blade.php'));
    $methodStart = strpos($source, 'async importDocx(event)');
    $methodEnd = strpos($source, 'async boot()', $methodStart);
    $method = substr($source, $methodStart, $methodEnd - $methodStart);

    $waitAt = strpos($method, 'await this.flushForSave()');
    $teardownAt = strpos($method, 'this.teardown();');
    $uploadAt = strpos($method, 'await fetch(this.importUrl');

    // The outgoing session's final callback can write old bytes. Waiting for
    // it before import ensures the replacement remains the newest revision.
    expect($waitAt)->toBeInt()
        ->and($teardownAt)->toBeGreaterThan($waitAt)
        ->and($uploadAt)->toBeGreaterThan($teardownAt);
});

it('creates, switches, replaces and removes independent printed template slots', function () {
    $form = editorForm();
    $admin = recordsUser(2);
    $templates = app(FormPrintTemplateService::class);
    $original = $templates->resolve($form);
    $originalBytes = Storage::disk('public')->get($original->docx_path);

    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'form_id' => $form->getKey(),
        'name' => $form->name,
        'fields' => [],
    ])->assertOk()->json();
    expect($sync['slots'])->toHaveCount(1);
    $first = $sync['slots'][0];

    $added = $this->post($sync['addUrl'], [
        'docx' => UploadedFile::fake()->createWithContent('second.docx', $originalBytes),
    ], ['Accept' => 'application/json'])->assertOk()->json();
    expect($added['slots'])->toHaveCount(2);
    $second = $added['slots'][1];
    expect($second['name'])->toBe('second.docx');

    $configA = $this->getJson($first['configUrl'])->assertOk()->json();
    $configB = $this->getJson($second['configUrl'])->assertOk()->json();
    expect($configA['config']['document']['key'])->not->toBe($configB['config']['document']['key']);
    $this->getJson($second['versionUrl'])->assertJson(['version' => 1]);

    $this->post($second['importUrl'], [
        'docx' => UploadedFile::fake()->createWithContent('updated.docx', $originalBytes),
    ], ['Accept' => 'application/json'])->assertOk();
    $this->getJson($second['versionUrl'])->assertJson(['version' => 2]);
    $this->getJson($first['versionUrl'])->assertJson(['version' => 1]);

    $templates->adoptDraft($form, $sync['draftId'], (int) $admin->getKey());
    expect($templates->activeTemplates($form))->toHaveCount(2);
    expect($templates->activeTemplates($form)->pluck('template_name')->all())
        ->toBe([$form->name, 'updated.docx']);

    $sync2 = $this->postJson(route('admin.form-builder.draft.sync'), [
        'form_id' => $form->getKey(), 'name' => $form->name, 'fields' => [],
    ])->assertOk()->json();
    $this->deleteJson($sync2['slots'][1]['removeUrl'])->assertOk();
    $this->deleteJson($sync2['slots'][0]['removeUrl'])->assertUnprocessable();
    $templates->adoptDraft($form, $sync2['draftId'], (int) $admin->getKey());
    expect($templates->activeTemplates($form))->toHaveCount(1)
        ->and($form->templates()->count())->toBe(2);
});

it('scopes Document Server saves to the selected draft slot', function () {
    $admin = recordsUser(2);
    $sync = $this->actingAs($admin)->postJson(route('admin.form-builder.draft.sync'), [
        'name' => 'Two pages', 'fields' => [],
    ])->assertOk()->json();
    $source = app(FormPrintTemplateService::class)->readDraftDocx($sync['draftId']);
    $added = $this->post($sync['addUrl'], [
        'docx' => UploadedFile::fake()->createWithContent('second.docx', $source),
    ], ['Accept' => 'application/json'])->assertOk()->json();
    $first = $added['slots'][0];
    $second = $added['slots'][1];
    $config = $this->getJson($second['configUrl'])->assertOk()->json('config');
    $callback = $config['editorConfig']['callbackUrl'];
    $document = $config['document']['url'];
    $this->get($document)->assertOk()->assertSee($source, false);

    Http::fake(['*' => Http::response('SECOND-SLOT-EDITED')]);
    $body = app(OnlyOfficeService::class)->sign([
        'status' => 2, 'url' => 'http://onlyoffice/cache/files/output.docx',
    ]);
    $this->postJson($callback, ['token' => $body])->assertOk()->assertJson(['error' => 0]);

    $this->getJson($first['versionUrl'])->assertJson(['version' => 1, 'closed' => false]);
    $this->getJson($second['versionUrl'])->assertJson(['version' => 2, 'closed' => true]);
    $templates = app(FormPrintTemplateService::class);
    expect($templates->readSlotDocx($sync['draftId'], $first['id']))->toBe($source)
        ->and($templates->readSlotDocx($sync['draftId'], $second['id']))->toBe('SECOND-SLOT-EDITED');
});

it('rejects an import that is not a real .docx', function () {
    $form = editorForm();
    $template = app(FormPrintTemplateService::class)->resolve($form);
    $versionBefore = $template->version;

    $this->actingAs(recordsUser(2))
        ->post(route('admin.form-builder.printed-template.import', $form), [
            'docx' => UploadedFile::fake()->createWithContent('notes.docx', 'this is not a zip'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);

    // A rejected upload must not bump the revision.
    expect($template->refresh()->version)->toBe($versionBefore);
});

it('keeps the import endpoint behind admin auth', function () {
    $form = editorForm();

    $this->actingAs(recordsUser(3))
        ->post(route('admin.form-builder.printed-template.import', $form), [
            'docx' => UploadedFile::fake()->create('x.docx', 10),
        ])
        ->assertForbidden();
});

it('no longer exposes the legacy template export/import routes', function () {
    expect(fn () => route('admin.form-builder.template.export-docx'))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
    expect(fn () => route('admin.form-builder.template.import-docx'))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});
