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
use App\Services\SignatureReferenceService;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
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

/**
 * Writes a minimal partial `.docx` (only `word/document.xml` is read) to the
 * session's storage directory, wrapping the given table rows.
 */
function manualPartialDocx(string $sessionId, string $rows): void
{
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:tbl><w:tblGrid><w:gridCol w:w="4680"/><w:gridCol w:w="4680"/></w:tblGrid>'
        .$rows.'</w:tbl>'
        .'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
        .'<w:pgMar w:left="1440" w:right="1440" w:top="1440" w:bottom="1440"/></w:sectPr>'
        .'</w:body></w:document>';

    $tmp = tempnam(sys_get_temp_dir(), 'partial').'.docx';
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $document);
    $zip->close();

    Storage::disk('public')->put('manual-form/'.$sessionId.'/partial.docx', file_get_contents($tmp));
    @unlink($tmp);
}

/** A light-background PNG with a dark handwritten-style scrawl (has real ink). */
function manualSignaturePng(): string
{
    $img = imagecreatetruecolor(200, 80);
    imagefilledrectangle($img, 0, 0, 199, 79, imagecolorallocate($img, 255, 255, 255));
    $ink = imagecolorallocate($img, 12, 12, 20);
    imagesetthickness($img, 3);
    imageline($img, 12, 60, 60, 20, $ink);
    imageline($img, 60, 20, 92, 60, $ink);
    imageline($img, 92, 60, 150, 24, $ink);
    imageline($img, 20, 46, 168, 46, $ink);

    ob_start();
    imagepng($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    return $bytes;
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

it('starts from an active stored template without legacy PDF HTML', function () {
    Queue::fake();
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $org = recordsOrganization('No Legacy HTML Org');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm('purpose', 'stored-template-only');
    $template = app(\App\Services\FormPrintTemplateService::class)->resolve($form, (int) $officer->getKey());
    $form->pdf_template = [];
    $form->save();

    $response = $this->actingAs($officer)->postJson(route('manual.start', $form->route_name), []);
    $response->assertOk();
    $session = ManualFormSession::findOrFail($response->json('session_id'));
    expect($session->documents)->toHaveCount(1)
        ->and($session->documents->first()->template_id)->toBe($template->getKey());
});

it('creates one ordered document per active printed template', function () {
    Queue::fake();
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $org = recordsOrganization('Multi Template Org');
    $officer = manualOfficer((int) $org->getKey());
    $form = manualForm('purpose', 'multi-manual-start');
    $first = app(\App\Services\FormPrintTemplateService::class)->resolve($form, (int) $officer->getKey());
    $second = $first->replicate();
    $second->docx_path = 'templates/second-manual.docx';
    $second->slot_order = 2;
    $second->save();
    Storage::disk('public')->put($second->docx_path, Storage::disk('public')->get($first->docx_path));

    $response = $this->actingAs($officer)->postJson(route('manual.start', $form->route_name), []);
    $response->assertOk();
    $session = ManualFormSession::findOrFail($response->json('session_id'));
    expect($session->documents->pluck('template_id')->all())
        ->toBe(app(\App\Services\FormPrintTemplateService::class)->activeTemplates($form)->pluck('id')->all())
        ->and($session->documents)->toHaveCount(2);
});

it('backfills a legacy session into a first document during migration', function () {
    $previous = DB::getDefaultConnection();
    config(['database.connections.sqlite.database' => ':memory:']);
    DB::setDefaultConnection('sqlite');
    try {
        \Illuminate\Support\Facades\Schema::create('templates', function ($table) {
            $table->id();
        });
        \Illuminate\Support\Facades\Schema::create('manual_form_sessions', function ($table) {
            $table->uuid('id')->primary();
            foreach (['template_id', 'template_version', 'status', 'baseline_schema',
                'session_schema', 'partial_pdf_path', 'partial_pdf_hash', 'page_meta',
                'scan_paths', 'aligned_page_paths', 'parse_result', 'parse_confidence',
                'parse_warnings', 'parse_error', 'parse_model'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        $id = (string) Str::uuid();
        DB::table('manual_form_sessions')->insert([
            'id' => $id, 'status' => 'review',
            'partial_pdf_path' => 'manual-form/old/partial.pdf',
            'scan_paths' => '["manual-form/old/scan.png"]',
            'parse_result' => '{"values":{"purpose":"Old value"}}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_09_25_000001_create_manual_form_session_documents.php');
        $migration->up();
        $document = DB::table('manual_form_session_documents')->where('manual_form_session_id', $id)->first();
        expect($document)->not->toBeNull()
            ->and($document->position)->toBe(0)
            ->and($document->partial_pdf_path)->toBe('manual-form/old/partial.pdf')
            ->and(json_decode($document->scan_paths, true))->toBe(['manual-form/old/scan.png'])
            ->and(json_decode($document->parse_result, true)['values']['purpose'])->toBe('Old value');
    } finally {
        DB::setDefaultConnection($previous);
        DB::disconnect('sqlite');
    }
});

it('merges separate document results and does not require a scan for a document without paper fields', function () {
    $form = manualForm('purpose', 'multi-manual-review');
    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_AWAITING_SCAN,
        'extractable_fields' => [['key' => 'purpose', 'label' => 'Purpose', 'required' => true]],
    ]);
    $scan = $session->documents()->create([
        'position' => 0, 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
        'session_schema' => ['extractable_fields' => [['key' => 'purpose']]],
    ]);
    $noScan = $session->documents()->create([
        'position' => 1, 'status' => ManualFormSession::STATUS_REVIEW,
        'session_schema' => ['extractable_fields' => []],
    ]);
    app(\App\Services\ManualForm\ManualFormSessionService::class)->syncDocuments($session);
    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_AWAITING_SCAN);

    $scan->update([
        'status' => ManualFormSession::STATUS_REVIEW,
        'parse_result' => ['values' => ['purpose' => 'Sports fest'], 'unresolved' => []],
        'parse_confidence' => ['purpose' => 0.9],
        'scan_paths' => ['manual-form/test/scan.png'],
    ]);
    app(\App\Services\ManualForm\ManualFormSessionService::class)->syncDocuments($session);
    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_REVIEW)
        ->and($session->parse_result['values']['purpose'])->toBe('Sports fest')
        ->and($session->scan_paths)->toBe(['manual-form/test/scan.png'])
        ->and($noScan->refresh()->scan_paths)->toBeNull();
});

it('generates document-specific schemas and skips scans without hand-fill fields', function () {
    $form = manualForm('purpose', 'manual-schema-per-document');
    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PREPARING,
        'extractable_fields' => [
            ['key' => 'purpose', 'label' => 'Purpose', 'required' => true, 'type' => 'text',
                'paper_support' => \App\Forms\FieldType::PAPER_EXTRACT],
        ],
    ]);
    $paper = $session->documents()->create([
        'position' => 0, 'status' => ManualFormSession::STATUS_PREPARING,
        'baseline_schema' => ['fields' => [
            ['key' => 'purpose', 'writable_area' => 'present', 'page' => 0],
        ]],
    ]);
    $digital = $session->documents()->create([
        'position' => 1, 'status' => ManualFormSession::STATUS_PREPARING,
        'baseline_schema' => ['fields' => []],
    ]);
    $generator = app(\App\Services\ManualForm\ManualSessionSchemaGenerator::class);
    $generator->generate((string) $session->getKey(), $paper->getKey());
    $generator->generate((string) $session->getKey(), $digital->getKey());
    expect($paper->refresh()->status)->toBe(ManualFormSession::STATUS_AWAITING_SCAN)
        ->and($digital->refresh()->status)->toBe(ManualFormSession::STATUS_REVIEW)
        ->and($session->refresh()->status)->toBe(ManualFormSession::STATUS_AWAITING_SCAN);
});

it('rejects uncovered required fields and isolates documents by session ownership', function () {
    $form = manualForm('purpose', 'multi-manual-authorization');
    $owner = recordsUser(3);
    $session = manualSession($form, [
        'user_id' => $owner->getKey(),
        'extractable_fields' => [['key' => 'purpose', 'label' => 'Purpose', 'required' => true]],
    ]);
    $document = $session->documents()->create([
        'position' => 0, 'status' => ManualFormSession::STATUS_REVIEW,
        'session_schema' => ['extractable_fields' => []],
    ]);
    app(\App\Services\ManualForm\ManualFormSessionService::class)->syncDocuments($session);
    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_FAILED)
        ->and($session->parse_error)->toContain('Purpose');

    Route::get('/test-manual/{session}/documents/{document}', [
        \App\Http\Controllers\ManualFormSessionController::class, 'statusDocument',
    ])->middleware(\Illuminate\Routing\Middleware\SubstituteBindings::class);
    $url = "/test-manual/{$session->getKey()}/documents/{$document->getKey()}";
    $this->getJson($url)->assertNotFound();
    $this->actingAs($owner)->getJson($url)->assertOk()->assertJson(['status' => ManualFormSession::STATUS_REVIEW]);
    $other = manualSession($form, ['user_id' => $owner->getKey()]);
    $this->getJson("/test-manual/{$other->getKey()}/documents/{$document->getKey()}")->assertNotFound();
});

it('parses each document against only its own scan and waits for all documents before review', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = manualForm('purpose', 'multi-manual-scans');
    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PARSING,
        'extractable_fields' => [
            ['key' => 'purpose', 'required' => true, 'label' => 'Purpose'],
            ['key' => 'venue', 'required' => true, 'label' => 'Venue'],
        ],
    ]);
    $documents = collect(['purpose', 'venue'])->map(function ($key, $position) use ($session) {
        $prefix = "manual-form/{$session->getKey()}/documents/{$position}";
        Storage::disk('public')->put("{$prefix}/reference.png", "ref-{$key}");
        Storage::disk('public')->put("{$prefix}/scan.png", "scan-{$key}");

        return $session->documents()->create([
            'position' => $position,
            'status' => ManualFormSession::STATUS_PARSING,
            'page_meta' => [['index' => 0, 'path' => "{$prefix}/reference.png"]],
            'scan_paths' => ["{$prefix}/scan.png"],
            'session_schema' => ['extractable_fields' => [['key' => $key, 'type' => 'text', 'page' => 0]]],
        ]);
    });

    $ocr = Mockery::mock(OcrClient::class);
    $ocr->shouldReceive('alignPages')->twice()->andReturnUsing(function ($references, $scans) {
        expect($references)->toBe([base64_encode(str_replace('scan-', 'ref-', $scans[0]))]);

        return ['ok' => true, 'pages' => [
            ['index' => 0, 'matched' => true, 'image' => base64_encode($scans[0])],
        ]];
    });
    $vision = Mockery::mock(DocumentVisionClient::class);
    $vision->shouldReceive('extract')->twice()->andReturnUsing(function ($input) {
        $key = $input['fields'][0]['key'];

        return ['ok' => true, 'values' => [$key => "read-{$key}"], 'confidence' => [$key => 0.8]];
    });
    $parser = new ManualScanParser($ocr, $vision, Mockery::mock(PdfRasterizer::class));
    $parser->parse((string) $session->getKey(), $documents[0]->getKey());
    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_PARSING);
    $parser->parse((string) $session->getKey(), $documents[1]->getKey());
    expect($session->refresh()->status)->toBe(ManualFormSession::STATUS_REVIEW)
        ->and($session->parse_result['values'])->toEqual([
            'purpose' => 'read-purpose', 'venue' => 'read-venue',
        ]);
});

it('stores uploaded scans under their own document and rejects cross-session uploads', function () {
    Queue::fake();
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = manualForm('purpose', 'manual-document-upload');
    $owner = recordsUser(3);
    $session = manualSession($form, ['user_id' => $owner->getKey(),
        'status' => ManualFormSession::STATUS_AWAITING_SCAN,
        'extractable_fields' => [['key' => 'purpose', 'label' => 'Purpose', 'required' => true]],
    ]);
    $document = $session->documents()->create([
        'position' => 0, 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
        'session_schema' => ['extractable_fields' => [['key' => 'purpose']]],
    ]);
    Route::post('/test-manual/{session}/documents/{document}/scans', [
        \App\Http\Controllers\ManualFormSessionController::class, 'uploadDocument',
    ])->middleware(\Illuminate\Routing\Middleware\SubstituteBindings::class);
    $url = "/test-manual/{$session->getKey()}/documents/{$document->getKey()}/scans";
    $this->postJson($url, ['scans' => [UploadedFile::fake()->image('page.png')]])->assertNotFound();
    $this->actingAs($owner)->postJson($url, [
        'scans' => [UploadedFile::fake()->image('page.png')],
    ])->assertOk();
    $path = $document->refresh()->scan_paths[0];
    expect($path)->toContain("manual-form/{$session->getKey()}/documents/{$document->getKey()}/scans/")
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
    Queue::assertPushed(ParseManualFormScan::class,
        fn ($job) => $job->documentId === $document->getKey());
    $other = manualSession($form, ['user_id' => $owner->getKey()]);
    $this->postJson("/test-manual/{$other->getKey()}/documents/{$document->getKey()}/scans", [
        'scans' => [UploadedFile::fake()->image('page.png')],
    ])->assertNotFound();
});

it('shows separately downloadable documents on the manual resume page', function () {
    $form = manualForm('purpose', 'manual-multi-resume');
    $owner = recordsUser(3);
    $session = manualSession($form, [
        'user_id' => $owner->getKey(), 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
    ]);
    foreach ([0, 1] as $position) {
        $session->documents()->create([
            'position' => $position, 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
            'partial_pdf_path' => "manual-form/{$session->getKey()}/documents/{$position}/partial.pdf",
            'session_schema' => ['extractable_fields' => [['key' => 'purpose']]],
        ]);
    }
    $this->actingAs($owner)->get(route('manual.show', $session))
        ->assertOk()->assertSee('Document 1')->assertSee('Document 2')
        ->assertSee(route('manual.documents.download', [$session, $session->documents[0]]))
        ->assertSee(route('manual.documents.download', [$session, $session->documents[1]]));
});

it('lists every printable document separately on the officer drafts page', function () {
    $form = manualForm('purpose', 'manual-multi-drafts');
    $org = recordsOrganization('Draft Links Org');
    $owner = manualOfficer((int) $org->getKey());
    $session = manualSession($form, [
        'user_id' => $owner->getKey(), 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
    ]);
    foreach ([0, 1] as $position) {
        $session->documents()->create([
            'position' => $position, 'status' => ManualFormSession::STATUS_AWAITING_SCAN,
            'partial_pdf_path' => "manual-form/{$session->getKey()}/documents/{$position}/partial.pdf",
        ]);
    }
    $response = $this->actingAs($owner)->get(route('manual.drafts'));
    $response->assertOk()->assertSee('PDF 1')->assertSee('PDF 2')
        ->assertSee(route('manual.documents.download', [$session, $session->documents[0]]))
        ->assertSee(route('manual.documents.download', [$session, $session->documents[1]]));
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

it('crops a detected signature from the scan and stores it for review', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = manualForm('adviser_sig', 'sig-scan-form');
    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PARSING,
        'page_meta' => [['index' => 0, 'path' => 'manual-form/ref.png']],
        'scan_paths' => ['manual-form/scan.png'],
        'session_schema' => ['extractable_fields' => [
            ['key' => 'adviser_sig', 'type' => 'signature', 'page' => 0, 'bounds' => [0.05, 0.1, 0.85, 0.75], 'bounds_source' => 'render'],
        ]],
    ]);
    Storage::disk('public')->put('manual-form/ref.png', 'ref-bytes');
    Storage::disk('public')->put('manual-form/scan.png', manualSignaturePng());

    $ocr = Mockery::mock(OcrClient::class);
    $ocr->shouldReceive('alignPages')->once()->andReturn([
        'ok' => true,
        'pages' => [[
            'index' => 0,
            'matched' => true,
            'image' => 'data:image/png;base64,'.base64_encode(manualSignaturePng()),
        ]],
        'warnings' => [],
    ]);

    $vision = Mockery::mock(DocumentVisionClient::class);
    $vision->shouldReceive('extract')->once()->andReturn([
        'ok' => true,
        'model' => 'test-model',
        'values' => [],
        'signatures' => [
            'adviser_sig' => ['present' => true, 'page' => 0, 'bounds' => [0.0, 0.0, 1.0, 1.0]],
        ],
        'unresolved' => [],
        'confidence' => [],
        'warnings' => [],
    ]);

    (new ManualScanParser($ocr, $vision, Mockery::mock(PdfRasterizer::class)))
        ->parse((string) $session->getKey());

    $session->refresh();
    $stored = $session->parse_result['signature_images']['adviser_sig'] ?? null;

    expect($session->status)->toBe(ManualFormSession::STATUS_REVIEW)
        ->and($stored)->not->toBeNull()
        ->and(Storage::disk('public')->exists($stored))->toBeTrue();
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

it('uses the manual draft organization when verifying expected position signatures', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = manualForm('title', 'manual-president-signature');
    FormDescription::create([
        'form_id' => $form->id,
        'field_key' => 'organization_id',
        'field_label' => 'Organization',
        'field_type' => 'org-select',
        'is_required' => false,
        'field_order' => 2,
    ]);
    FormDescription::create([
        'form_id' => $form->id,
        'field_key' => 'president_signature',
        'field_label' => 'President Signature',
        'field_type' => 'signature',
        'is_required' => true,
        'field_order' => 3,
        'field_options' => ['match_mode' => 'compare', 'expected_positions' => ['President']],
    ]);

    $orgA = recordsOrganization('Manual Compare A');
    $orgB = recordsOrganization('Manual Compare B');

    $presidentA = recordsUser(3, [
        'first_name' => 'Manual',
        'last_name' => 'President',
        'signature_path' => 'signatures/manual-president-a.png',
    ]);
    Storage::disk(SignatureImage::disk())->put('signatures/manual-president-a.png', 'expected-signature-bytes');
    DB::table('organization_officers')->insert([
        'role' => 'president',
        'position' => 'President',
        'organization' => (int) $orgA->getKey(),
        'user' => (int) $presidentA->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $presidentB = recordsUser(3, [
        'first_name' => 'Other',
        'last_name' => 'President',
        'signature_path' => 'signatures/manual-president-b.png',
    ]);
    Storage::disk(SignatureImage::disk())->put('signatures/manual-president-b.png', 'other-signature-bytes');
    DB::table('organization_officers')->insert([
        'role' => 'president',
        'position' => 'President',
        'organization' => (int) $orgB->getKey(),
        'user' => (int) $presidentB->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $submitter = manualOfficer((int) $orgA->getKey());
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) $orgB->getKey(),
        'user' => (int) $submitter->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $reference = app(SignatureReferenceService::class)->syncFromProfile($presidentA->profile()->first());
    $session = manualSession($form, [
        'user_id' => (int) $submitter->getKey(),
        'draft_payload' => ['organization_id' => (string) $orgA->getKey()],
    ]);

    Http::fake(['*/signature-identify' => Http::response([
        'match' => true,
        'best' => ['id' => (int) $reference->reference_id, 'score' => 0.97],
    ], 200)]);

    $this->actingAs($submitter)
        ->post(route('forms.render.submit', $form->route_name), [
            'title' => 'Manual event',
            'president_signature' => signatureDataUrl(8),
            'manual_session_id' => (string) $session->getKey(),
        ])
        ->assertSessionHasNoErrors();
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

it('blocks a session when a populated value leaves a required field no room to write', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = manualForm('adviser2_name', 'distorted-manual-form');

    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PREPARING,
        'page_meta' => [],
        'baseline_schema' => ['fields' => [[
            'key' => 'adviser2_name',
            'label' => 'Adviser 2 Name',
            'type' => 'text',
            'paper_support' => 'extract',
            'writable_area' => 'present',
            'cell_path' => ['block' => 0, 'row' => 0, 'col' => 1, 'colspan' => 1, 'rowspan' => 1],
        ]]],
        'extractable_fields' => [[
            'key' => 'adviser2_name',
            'label' => 'Adviser 2 Name',
            'type' => 'text',
            'paper_support' => 'extract',
            'required' => true,
        ]],
    ]);

    // A long value printed on the same line as the field consumes the cell's
    // width, so there is no room left to write the required field by hand.
    manualPartialDocx((string) $session->getKey(),
        '<w:tr><w:trPr><w:trHeight w:val="600" w:hRule="exact"/></w:trPr>'
        .'<w:tc><w:p><w:r><w:t>Adviser 1 Name:</w:t></w:r></w:p></w:tc>'
        .'<w:tc><w:p><w:r><w:t>Full legal name of the faculty adviser assigned: {{adviser2_name}}</w:t></w:r></w:p></w:tc></w:tr>'
    );

    app(\App\Services\ManualForm\ManualSessionSchemaGenerator::class)->generate((string) $session->getKey());

    $session->refresh();
    expect($session->status)->toBe(ManualFormSession::STATUS_FAILED)
        ->and($session->parse_error)->toContain('Adviser 2 Name');
});

it('re-measures a table field against the frozen partial and awaits the scan when it fits', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = manualForm('adviser2_name', 'fitting-manual-form');

    $session = manualSession($form, [
        'status' => ManualFormSession::STATUS_PREPARING,
        'page_meta' => [],
        'baseline_schema' => ['fields' => [[
            'key' => 'adviser2_name',
            'label' => 'Adviser 2 Name',
            'type' => 'text',
            'paper_support' => 'extract',
            'writable_area' => 'present',
            'bounds' => [0.5, 0.1, 0.88, 0.16],
            'bounds_source' => 'render',
            'cell_path' => ['block' => 0, 'row' => 1, 'col' => 1, 'colspan' => 1, 'rowspan' => 1],
        ]]],
        'extractable_fields' => [[
            'key' => 'adviser2_name',
            'label' => 'Adviser 2 Name',
            'type' => 'text',
            'paper_support' => 'extract',
            'required' => true,
        ]],
    ]);

    manualPartialDocx((string) $session->getKey(),
        '<w:tr><w:trPr><w:trHeight w:val="600" w:hRule="exact"/></w:trPr>'
        .'<w:tc><w:p><w:r><w:t>Adviser 1 Name:</w:t></w:r></w:p></w:tc>'
        .'<w:tc><w:p><w:r><w:t>Short name</w:t></w:r></w:p></w:tc></w:tr>'
        .'<w:tr><w:trPr><w:trHeight w:val="600" w:hRule="exact"/></w:trPr>'
        .'<w:tc><w:p><w:r><w:t>Adviser 2 Name:</w:t></w:r></w:p></w:tc>'
        .'<w:tc><w:p><w:r><w:t>{{adviser2_name}}</w:t></w:r></w:p></w:tc></w:tr>'
    );

    app(\App\Services\ManualForm\ManualSessionSchemaGenerator::class)->generate((string) $session->getKey());

    $session->refresh();
    expect($session->status)->toBe(ManualFormSession::STATUS_AWAITING_SCAN)
        ->and($session->parse_error)->toBeNull();

    $field = collect($session->session_schema['extractable_fields'])->firstWhere('key', 'adviser2_name');
    expect($field['bounds'][0])->toBe(0.5);
});
