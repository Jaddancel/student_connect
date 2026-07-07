<?php

use App\Models\IdTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A valid store/update payload (native pixels within a 1000x600 reference).
 */
function idTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'University X Student ID 2026',
        'image_path' => 'id-templates/reference.jpg',
        'image_width' => 1000,
        'image_height' => 600,
        'is_active' => true,
        'is_default' => true,
        'zones' => [[
            'name' => 'student_id',
            'label' => 'Student ID Number',
            'x1' => 100, 'y1' => 300, 'x2' => 500, 'y2' => 360,
            'regex' => '\\d{2}-\\d{4}-\\d{3}',
            'field' => 'student_id',
        ]],
    ], $overrides);
}

it('blocks non-superadmins from the index', function () {
    $this->actingAs(recordsUser(2))->get(route('superadmin.id-templates.index'))->assertForbidden();
    $this->actingAs(recordsUser(3))->get(route('superadmin.id-templates.index'))->assertForbidden();
});

it('lets a superadmin open the index', function () {
    $this->actingAs(recordsUser(1))->get(route('superadmin.id-templates.index'))->assertOk();
});

it('stores a template preserving native zone coordinates', function () {
    $this->actingAs(recordsUser(1))
        ->postJson(route('superadmin.id-templates.store'), idTemplatePayload())
        ->assertOk()
        ->assertJsonStructure(['message', 'redirect']);

    $template = IdTemplate::query()->firstOrFail();

    expect($template->zones)->toBeArray()->toHaveCount(1);
    expect($template->zones[0]['x1'])->toBe(100);
    expect($template->zones[0]['x2'])->toBe(500);
    expect($template->zones[0]['field'])->toBe('student_id');
    expect($template->is_default)->toBeTrue();
});

it('rejects a zero/negative-width zone', function () {
    $payload = idTemplatePayload(['zones' => [[
        'name' => 'bad', 'label' => 'Bad',
        'x1' => 500, 'y1' => 300, 'x2' => 400, 'y2' => 360,
        'regex' => null, 'field' => 'student_id',
    ]]]);

    $this->actingAs(recordsUser(1))
        ->postJson(route('superadmin.id-templates.store'), $payload)
        ->assertStatus(422);
});

it('rejects a zone outside the image bounds', function () {
    $payload = idTemplatePayload(['zones' => [[
        'name' => 'oob', 'label' => 'Out',
        'x1' => 100, 'y1' => 300, 'x2' => 1200, 'y2' => 360,
        'regex' => null, 'field' => 'student_id',
    ]]]);

    $this->actingAs(recordsUser(1))
        ->postJson(route('superadmin.id-templates.store'), $payload)
        ->assertStatus(422);
});

it('rejects an uncompilable regex', function () {
    $payload = idTemplatePayload();
    $payload['zones'][0]['regex'] = '('; // unbalanced

    $this->actingAs(recordsUser(1))
        ->postJson(route('superadmin.id-templates.store'), $payload)
        ->assertStatus(422);
});

it('enforces a single default template', function () {
    $admin = recordsUser(1);

    $this->actingAs($admin)->postJson(route('superadmin.id-templates.store'), idTemplatePayload(['name' => 'First']))->assertOk();
    $this->actingAs($admin)->postJson(route('superadmin.id-templates.store'), idTemplatePayload(['name' => 'Second']))->assertOk();

    expect(IdTemplate::query()->where('is_default', true)->count())->toBe(1);
    expect(IdTemplate::query()->where('is_default', true)->value('name'))->toBe('Second');
});

it('uploads a reference image to the id-templates directory', function () {
    Storage::fake('public');

    $response = $this->actingAs(recordsUser(1))
        ->post(route('superadmin.id-templates.upload-image'), [
            'asset' => UploadedFile::fake()->image('id.jpg', 1000, 600),
        ]);

    $response->assertOk()->assertJsonStructure(['path']);
    $path = $response->json('path');
    expect($path)->toStartWith('id-templates/');
    Storage::disk('public')->assertExists($path);
});

it('auto-scans an uploaded ID photo and returns the extracted student_id', function () {
    Http::fake(['*/scan' => Http::response(['fields' => ['student_id' => '21-1234-567'], 'raw' => []], 200)]);

    IdTemplate::query()->create(idTemplatePayload());

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson(['student_id' => '21-1234-567']);
});

it('returns universal-keyed fields for the signup wizard to prefill', function () {
    // Sidecar returns raw text keyed by each zone's name; the controller maps
    // those onto the zones' universal `field` keys.
    Http::fake(['*/scan' => Http::response(['fields' => [
        'student_id' => '21-1234-567',
        'first_name' => 'JUAN',
        'birthday' => '2003-05-01',
    ], 'raw' => []], 200)]);

    IdTemplate::query()->create(idTemplatePayload(['zones' => [
        ['name' => 'student_id', 'label' => 'Student ID', 'x1' => 10, 'y1' => 10, 'x2' => 200, 'y2' => 60, 'regex' => null, 'field' => 'student_id'],
        ['name' => 'first_name', 'label' => 'First Name', 'x1' => 10, 'y1' => 70, 'x2' => 200, 'y2' => 120, 'regex' => null, 'field' => 'first_name'],
        ['name' => 'birthday', 'label' => 'Birthday', 'x1' => 10, 'y1' => 130, 'x2' => 200, 'y2' => 180, 'regex' => null, 'field' => 'birthday'],
    ]]));

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson([
        'student_id' => '21-1234-567',
        'fields' => [
            'student_id' => '21-1234-567',
            'first_name' => 'JUAN',
            'birthday' => '2003-05-01',
        ],
    ]);
});

it('degrades gracefully when the OCR sidecar is unreachable', function () {
    Http::fake(function () {
        throw new ConnectionException('sidecar down');
    });

    IdTemplate::query()->create(idTemplatePayload());

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson(['student_id' => null]);
});

it('returns a note when no active template is configured', function () {
    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson(['student_id' => null, 'note' => 'no active template']);
});
