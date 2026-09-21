<?php

use App\Jobs\ParseManualFormScan;
use App\Jobs\PrepareManualFormSession;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\ManualFormSession;
use App\Models\User;
use App\Services\DocumentVision\DocumentVisionClient;
use App\Services\DocumentVision\DocumentVisionResultNormalizer;
use App\Services\DocumentVision\OllamaDocumentVisionClient;
use App\Services\ManualForm\ManualScanParser;
use App\Services\OcrClient;
use App\Services\PdfRasterizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function manualOfficer(int $orgId): User
{
    $user = recordsUser(3);
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

function manualForm(string $fieldKey = 'purpose', string $routeName = 'facility-request'): Form
{
    $form = Form::create([
        'name' => 'Facility Request',
        'route_name' => $routeName,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="'.$fieldKey.'">Value</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    FormDescription::create([
        'form_id' => $form->id, 'field_key' => $fieldKey, 'field_label' => ucfirst($fieldKey),
        'field_type' => 'text', 'is_required' => true, 'field_order' => 1,
    ]);

    return $form;
}

function manualSession(Form $form, array $attrs = []): ManualFormSession
{
    return ManualFormSession::create(array_merge([
        'id' => (string) Str::uuid(),
        'form_id' => $form->id,
        'status' => ManualFormSession::STATUS_REVIEW,
        'known_fields' => [],
        'parse_result' => ['values' => [], 'unresolved' => []],
        'expires_at' => now()->addDays(30),
    ], $attrs));
}

it('sends scan pages as repeated multipart fields expected by FastAPI', function () {
    config(['services.ocr.url' => 'http://ocr.test']);
    Http::fake([
        'http://ocr.test/align-pages' => Http::response([
            'ok' => true,
            'pages' => [],
            'warnings' => [],
        ]),
    ]);

    expect(app(OcrClient::class)->alignPages(['reference'], ['page-one', 'page-two'])['ok'])
        ->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return substr_count($body, 'name="pages"') === 2
            && ! str_contains($body, 'name="pages[');
    });
});

it('sends only bare-base64 aligned scans to Ollama', function () {
    config([
        'services.document_vision.provider' => 'ollama',
        'services.document_vision.url' => 'http://ollama.test',
        'services.document_vision.model' => 'vision-test',
        'services.document_vision.context_length' => 8192,
    ]);
    Http::fake([
        'http://ollama.test/api/chat' => Http::response([
            'message' => ['content' => '{"fields":{}}'],
        ]),
    ]);

    $client = new OllamaDocumentVisionClient(new DocumentVisionResultNormalizer);
    $client->extract([
        'known_values' => [],
        'fields' => [['key' => 'purpose', 'type' => 'text', 'page' => 0]],
        'pages' => [[
            'index' => 0,
            'reference_image' => 'cmVmZXJlbmNl',
            'scan_image' => 'data:image/png;base64,c2Nhbg==',
        ]],
    ]);

    Http::assertSent(fn ($request) => $request['options']['num_ctx'] === 8192
        && $request['messages'][1]['images'] === ['c2Nhbg==']);
});

it('queues partial PDF preparation instead of blocking the start request', function () {
    Queue::fake();
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Queued Manual Org');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm('purpose', 'queued-manual-form');
    app(\App\Services\FormPrintTemplateService::class)->resolve($form, (int) $officer->getKey());

    $response = $this->actingAs($officer)
        ->postJson(route('manual.start', $form->route_name), []);

    $response->assertOk()->assertJson([
        'ok' => true,
        'status' => ManualFormSession::STATUS_PREPARING,
    ]);

    $session = ManualFormSession::query()->findOrFail($response->json('session_id'));
    expect($session->partial_pdf_path)->toBeNull();
    Queue::assertPushed(
        PrepareManualFormSession::class,
        fn ($job) => $job->sessionId === (string) $session->getKey(),
    );
});

it('fails a manual session when queued preparation exhausts its attempts', function () {
    $form = manualForm('purpose', 'failed-manual-preparation');
    $session = manualSession($form, ['status' => ManualFormSession::STATUS_PREPARING]);

    (new PrepareManualFormSession((string) $session->getKey()))
        ->failed(new RuntimeException('worker timeout'));

    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_FAILED)
        ->and($session->parse_error)->toContain('Could not prepare');
});

it('fails a manual session when scan parsing times out', function () {
    $form = manualForm('purpose', 'timed-out-manual-scan');
    $session = manualSession($form, ['status' => ManualFormSession::STATUS_PARSING]);

    (new ParseManualFormScan((string) $session->getKey()))
        ->failed(new RuntimeException('worker timeout'));

    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_FAILED)
        ->and($session->parse_error)->toContain('could not be read in time');
});

it('rasterizes an uploaded PDF before aligning manual scan pages', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = manualForm('purpose', 'pdf-manual-scan');
    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PARSING,
        'page_meta' => [['index' => 0, 'path' => 'manual-form/reference.png']],
        'scan_paths' => ['manual-form/completed.pdf'],
        'session_schema' => ['extractable_fields' => []],
    ]);
    Storage::disk('public')->put('manual-form/reference.png', 'reference-png');
    Storage::disk('public')->put('manual-form/completed.pdf', '%PDF-scan');

    $rasterizer = Mockery::mock(PdfRasterizer::class);
    $rasterizer->shouldReceive('rasterize')->once()->andReturnUsing(function ($path, $outDir) {
        File::ensureDirectoryExists($outDir);
        File::put($outDir.'/page-1.png', 'rasterized-page');

        return ['ok' => true, 'pages' => [['index' => 0, 'path' => $outDir.'/page-1.png']]];
    });

    $ocr = Mockery::mock(OcrClient::class);
    $ocr->shouldReceive('alignPages')->once()->withArgs(
        fn ($references, $scans) => $references === [base64_encode('reference-png')]
            && $scans === ['rasterized-page'],
    )->andReturn([
        'ok' => true,
        'pages' => [[
            'index' => 0,
            'matched' => true,
            'image' => 'data:image/png;base64,'.base64_encode('aligned-page'),
        ]],
        'warnings' => [],
    ]);

    $vision = Mockery::mock(DocumentVisionClient::class);
    $vision->shouldReceive('extract')->once()->andReturn([
        'ok' => true,
        'model' => 'test-model',
        'values' => [],
        'signatures' => [],
        'unresolved' => [],
        'confidence' => [],
        'warnings' => [],
    ]);

    (new ManualScanParser($ocr, $vision, $rasterizer))->parse((string) $session->getKey());

    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_REVIEW)
        ->and($session->parse_error)->toBeNull();
});

it('links a reviewed manual draft to its submission and records provenance', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Manual Org');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm();

    $session = manualSession($form, [
        'user_id' => (int) $officer->getKey(),
        'partial_pdf_path' => 'manual-form/x/partial.pdf',
        'partial_pdf_hash' => 'sha256:abc',
        'parse_model' => 'qwen2.5vl:3b',
    ]);

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), [
            'purpose' => 'Sports fest',
            'manual_session_id' => (string) $session->getKey(),
        ])
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->firstOrFail();
    $source = $submission->payload['_manual_source'] ?? null;

    expect($source)->not->toBeNull()
        ->and($source['session_id'])->toBe((string) $session->getKey())
        ->and($source['parser_model'])->toBe('qwen2.5vl:3b');

    $session->refresh();
    expect($session->status)->toBe(ManualFormSession::STATUS_SUBMITTED)
        ->and((int) $session->form_submission_id)->toBe((int) $submission->getKey());
});

it('refuses to submit an already-submitted draft again', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Manual Org 2');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm();

    $session = manualSession($form, [
        'user_id' => (int) $officer->getKey(),
        'status' => ManualFormSession::STATUS_SUBMITTED,
    ]);

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), [
            'purpose' => 'Second attempt',
            'manual_session_id' => (string) $session->getKey(),
        ]);

    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(0);
});

it('shows only the owner their open drafts, filtered by form', function () {
    $org = recordsOrganization('Drafts Org');
    $officer = manualOfficer((int) $org->getKey());
    $other = manualOfficer((int) $org->getKey());
    $form = manualForm();
    $otherForm = manualForm('reason', 'other-form');

    $mine = manualSession($form, ['user_id' => (int) $officer->getKey(), 'status' => 'awaiting_scan']);
    manualSession($otherForm, ['user_id' => (int) $officer->getKey(), 'status' => 'awaiting_scan']);
    manualSession($form, ['user_id' => (int) $other->getKey(), 'status' => 'awaiting_scan']);
    // A submitted draft is not "open" and must not appear.
    manualSession($form, ['user_id' => (int) $officer->getKey(), 'status' => 'submitted']);

    $response = $this->actingAs($officer)->get(route('manual.drafts', ['form_id' => $form->id]))->assertOk();

    $ids = collect($response->viewData('drafts')->items())->pluck('id');
    expect($ids)->toContain($mine->id)->toHaveCount(1);
});

it('blocks admins from the officer drafts page', function () {
    $this->actingAs(recordsUser(2))->get(route('manual.drafts'))->assertForbidden();
});

it('404s a draft for a non-owner and honors a public resume token', function () {
    $org = recordsOrganization('Access Org');
    $owner = manualOfficer((int) $org->getKey());
    $intruder = manualOfficer((int) $org->getKey());
    $form = manualForm();

    $owned = manualSession($form, ['user_id' => (int) $owner->getKey(), 'status' => 'awaiting_scan']);
    $this->actingAs($intruder)->get(route('manual.show', $owned))->assertNotFound();
    $this->actingAs($owner)->get(route('manual.show', $owned))->assertOk();

    $token = Str::random(48);
    $public = manualSession($form, [
        'user_id' => null,
        'public_token_hash' => Hash::make($token),
        'status' => 'awaiting_scan',
    ]);

    $this->get(route('manual.show', $public))->assertNotFound();
    $this->get(route('manual.show', $public).'?t='.$token)->assertOk();
});

it('reaps expired abandoned drafts but keeps submitted ones', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Reap Org');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm();

    $expired = manualSession($form, [
        'user_id' => (int) $officer->getKey(),
        'status' => 'awaiting_scan',
        'expires_at' => now()->subDay(),
    ]);
    $submitted = manualSession($form, [
        'user_id' => (int) $officer->getKey(),
        'status' => 'submitted',
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('manual-sessions:cleanup')->assertSuccessful();

    expect(ManualFormSession::find($expired->id))->toBeNull()
        ->and(ManualFormSession::find($submitted->id))->not->toBeNull();
});
