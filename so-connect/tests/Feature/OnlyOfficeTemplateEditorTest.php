<?php

use App\Models\Form;
use App\Models\Template;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
        // Loaded by the browser, so it must use the public app URL.
        ->and($config['editorConfig']['plugins']['pluginsData'][0])->toContain('/plugin.json?token=');

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

    $this->get(route('onlyoffice.plugin-config', $form).'?token='.$token)
        ->assertOk()
        ->assertJsonPath('guid', 'asc.{8B3D2A41-6C7E-4F55-9E2B-1A4C9D0E7F31}');

    $palette = $this->get(route('onlyoffice.plugin', $form).'?token='.$token)->assertOk();

    expect($palette->getContent())->toContain('attendee_name')
        ->not->toContain('section_title');

    // The Document Server frames this, so SAMEORIGIN must not survive here.
    expect($palette->headers->get('Content-Security-Policy'))->toContain('frame-ancestors http://onlyoffice.test:8081');
    expect($palette->headers->get('X-Frame-Options'))->toBeNull();
});
