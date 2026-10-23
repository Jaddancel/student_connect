<?php

use App\Models\IdTemplate;
use App\Services\Scanning\VisionZoneReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.ocr.url' => 'http://ocr.test',
        'services.document_vision.provider' => 'ollama',
        'services.document_vision.url' => 'http://ollama.test',
        'services.document_vision.model' => 'vision-test',
        'services.document_vision.zone_reading' => true,
        'services.document_vision.zone_budget_seconds' => 25,
    ]);
});

function visionIdTemplate(): IdTemplate
{
    return IdTemplate::query()->create([
        'name' => 'Vision ID',
        'orientation' => 'vertical',
        'image_path' => 'id-templates/reference.jpg',
        'image_width' => 1000,
        'image_height' => 600,
        'is_active' => true,
        'is_default' => true,
        'zones' => [
            ['name' => 'name_zone', 'label' => 'Name', 'x1' => 10, 'y1' => 10, 'x2' => 600, 'y2' => 80, 'regex' => null, 'field' => 'full_name'],
            ['name' => 'student_id', 'label' => 'Student ID', 'x1' => 10, 'y1' => 100, 'x2' => 400, 'y2' => 160, 'regex' => '\\d{2}-\\d{4}-\\d{3}', 'field' => 'student_id'],
            ['name' => 'course', 'label' => 'Course', 'x1' => 10, 'y1' => 180, 'x2' => 400, 'y2' => 240, 'regex' => null, 'field' => 'course'],
            ['name' => 'sig', 'label' => 'Signature', 'x1' => 10, 'y1' => 300, 'x2' => 400, 'y2' => 400, 'field' => 'signature', 'type' => 'signature'],
        ],
        'back_image_path' => 'id-templates/reference-back.jpg',
        'back_image_width' => 1000,
        'back_image_height' => 600,
        'back_zones' => [],
    ]);
}

/**
 * Fake the model: answers keyed by the field label it was asked about.
 *
 * @param  array<string,mixed>  $answers  label => value (null = unreadable, 'FAIL' = HTTP 500)
 */
function fakeVisionAnswers(array $answers): void
{
    Http::fake([
        'http://ollama.test/api/chat' => function (HttpRequest $request) use ($answers) {
            $prompt = (string) ($request['messages'][1]['content'] ?? '');
            foreach ($answers as $label => $value) {
                if (str_contains($prompt, '"label":"'.$label.'"')) {
                    if ($value === 'FAIL') {
                        return Http::response(['error' => 'boom'], 500);
                    }

                    return Http::response(['message' => ['content' => json_encode(['value' => $value, 'confidence' => 0.93])]]);
                }
            }

            return Http::response(['message' => ['content' => '{"value":null,"confidence":0}']]);
        },
        'http://ocr.test/scan' => Http::response([
            'fields' => [
                'name_zone' => 'DANCELJANEDARTR',
                'student_id' => '21-1234-567',
                'course' => 'BS INFO TECH',
            ],
            'raw' => [],
            'images' => ['sig' => 'data:image/png;base64,c2ln'],
        ]),
    ]);
}

it('reads ID text zones with the vision model and falls back to OCR per field', function () {
    visionIdTemplate();
    fakeVisionAnswers([
        'Name' => 'DANCEL, JANE D.',
        'Student ID' => 'no id here',      // fails the zone regex → OCR value
        'Course' => 'FAIL',                // model error → OCR value
    ]);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 2000, 1200),
    ])->assertOk()->assertJson([
        'student_id' => '21-1234-567',
        'fields' => [
            'first_name' => 'JANE',
            'middle_name' => 'D.',
            'last_name' => 'DANCEL',
            'student_id' => '21-1234-567',
            'course' => 'BS INFO TECH',
        ],
        'images' => ['signature' => 'data:image/png;base64,c2ln'],
        'sources' => ['full_name' => 'vision', 'student_id' => 'ocr', 'course' => 'ocr'],
    ]);

    // Name disagreed with OCR so it was re-read in the other view to confirm
    // (2 calls); the others got one call each. The signature zone is never sent.
    $chats = collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/api/chat'));
    expect($chats)->toHaveCount(4)
        ->and($chats->every(fn ($pair) => $pair[0]['keep_alive'] === -1))->toBeTrue()
        ->and($chats->every(fn ($pair) => ! str_contains((string) $pair[0]['messages'][1]['content'], 'Signature')))->toBeTrue();
});

it('drops a model reading that two views of the zone disagree on', function () {
    visionIdTemplate();
    // A blurry zone: the model invents a different value per view.
    Http::fake([
        'http://ollama.test/api/chat' => function (HttpRequest $request) {
            $prompt = (string) $request['messages'][1]['content'];
            if (! str_contains($prompt, '"label":"Course"')) {
                return Http::response(['message' => ['content' => '{"value":null,"confidence":0}']]);
            }

            return Http::response(['message' => ['content' => json_encode([
                'value' => str_contains($prompt, 'red box') ? 'BS AGRICULTURE' : 'CALLEJO, VICTOR, IV',
                'confidence' => 0.95,
            ])]]);
        },
        'http://ocr.test/scan' => Http::response(['fields' => ['course' => ''], 'raw' => [], 'images' => []]),
    ]);

    $response = $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson(['sources' => ['course' => 'ocr']]);

    expect($response->json('fields'))->not->toHaveKey('course');
});

it('uses the model value for an ID number that passes the zone regex', function () {
    visionIdTemplate();
    fakeVisionAnswers(['Student ID' => '22-9876-543']);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson([
        'student_id' => '22-9876-543',
        'sources' => ['student_id' => 'vision'],
    ]);
});

it('keeps the OCR scan when model zone reading is disabled', function () {
    config(['services.document_vision.zone_reading' => false]);
    visionIdTemplate();
    fakeVisionAnswers(['Name' => 'SHOULD NOT BE USED']);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson([
        'student_id' => '21-1234-567',
        'sources' => ['full_name' => 'ocr', 'student_id' => 'ocr', 'course' => 'ocr'],
    ]);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
});

it('stops asking the model once the time budget is used up', function () {
    config(['services.document_vision.zone_budget_seconds' => 1]);
    visionIdTemplate();
    fakeVisionAnswers(['Name' => 'DANCEL, JANE D.']);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson([
        'sources' => ['full_name' => 'ocr', 'student_id' => 'ocr', 'course' => 'ocr'],
    ]);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
});

it('still returns model-read ID values when the OCR sidecar is down', function () {
    visionIdTemplate();
    Http::fake([
        'http://ollama.test/api/chat' => Http::response(['message' => ['content' => '{"value":"BS CIVIL ENG","confidence":0.9}']]),
        'http://ocr.test/scan' => Http::response([], 503),
    ]);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
    ])->assertOk()->assertJson([
        'fields' => ['course' => 'BS CIVIL ENG'],
        'note' => null,
    ]);
});

it('reads waiver text zones with the model without revealing expected values', function () {
    $officer = recordsUser(3);
    Http::fake([
        'http://ollama.test/api/chat' => function (HttpRequest $request) {
            $prompt = (string) $request['messages'][1]['content'];

            return Http::response(['message' => ['content' => json_encode(
                str_contains($prompt, '"key":"student_name"')
                    ? ['value' => 'Maria Clara Santos', 'confidence' => 0.9]
                    : ['value' => null, 'confidence' => 0],
            )]]);
        },
        'http://ocr.test/waiver-scan' => Http::response([
            'ok' => true,
            'fields' => ['student_name' => 'Mar1a C1ara 5antos', 'event_date' => '2026-11-05'],
            'images' => [],
            'signature' => true,
            'stamp' => true,
            'stamp_confidence' => 0.8,
        ]),
    ]);

    $png = UploadedFile::fake()->image('waiver.png', 800, 1100)->getContent();

    $response = $this->actingAs($officer)->postJson(route('waiver.scan'), [
        'image' => 'data:image/png;base64,'.base64_encode($png),
        'template' => [
            'reference' => ['width' => 800, 'height' => 1100],
            'zones' => [
                ['name' => 'student_name', 'x' => 50, 'y' => 100, 'w' => 500, 'h' => 60, 'type' => 'text'],
                ['name' => 'event_date', 'x' => 50, 'y' => 200, 'w' => 300, 'h' => 60, 'type' => 'text'],
                ['name' => 'seal', 'x' => 500, 'y' => 900, 'w' => 200, 'h' => 150, 'type' => 'stamp'],
            ],
        ],
        'expected' => [
            'student_name' => ['value' => 'Maria Clara Santos', 'type' => 'name'],
            'event_date' => ['value' => '2026-11-05', 'type' => 'date'],
        ],
    ])->assertOk();

    expect($response->json('valid'))->toBeTrue()
        ->and($response->json('validation.fields.student_name.match'))->toBeTrue();

    $chats = collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/api/chat'));
    expect($chats)->toHaveCount(2)
        ->and($chats->contains(fn ($pair) => str_contains((string) $pair[0]['messages'][1]['content'], 'It is a date')))->toBeTrue()
        ->and($chats->every(fn ($pair) => ! str_contains((string) $pair[0]['messages'][1]['content'], 'Maria Clara')))->toBeTrue();
});

it('infers what an ID zone holds from its field and label', function () {
    expect(VisionZoneReader::kindFor(['field' => 'student_id']))->toBe('id_number')
        ->and(VisionZoneReader::kindFor(['field' => 'birthday']))->toBe('date')
        ->and(VisionZoneReader::kindFor(['field' => 'home_address']))->toBe('address')
        ->and(VisionZoneReader::kindFor(['field' => 'full_name', 'label' => 'Name']))->toBe('name')
        ->and(VisionZoneReader::kindFor(['field' => 'course', 'label' => 'Course']))->toBe('text');
});

it('applies Python-authored zone regexes safely', function () {
    expect(VisionZoneReader::applyRegex('\\d{10}', 'ID 2023100741'))->toBe('2023100741')
        ->and(VisionZoneReader::applyRegex('\\d{10}', 'no digits'))->toBeNull()
        ->and(VisionZoneReader::applyRegex('', 'kept'))->toBe('kept')
        ->and(VisionZoneReader::applyRegex('(unclosed', 'kept as read'))->toBe('kept as read');
});
