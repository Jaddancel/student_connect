<?php

use App\Services\LlmService;
use Illuminate\Support\Facades\Http;

function makeLlm(): LlmService
{
    return new LlmService('http://test-ollama:11434', 'phi4-mini', 30, 8192, '10m');
}

/** Build an Ollama /api/chat response whose message content is $content. */
function ollamaReply(string $content): array
{
    return ['message' => ['content' => $content]];
}

function candidates(): array
{
    return [
        ['label' => 'Student Name', 'field_key' => 'student_name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
        ['label' => 'Instructions Heading', 'field_key' => 'instructions_heading', 'field_type' => 'text', 'is_required' => true, 'field_order' => 2],
        ['label' => 'Age', 'field_key' => 'age', 'field_type' => 'text', 'is_required' => true, 'field_order' => 3],
    ];
}

it('keeps a valid pruned subset returned by the model', function () {
    $kept = [
        ['label' => 'Student Name', 'field_key' => 'student_name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
        ['label' => 'Age', 'field_key' => 'age', 'field_type' => 'number', 'is_required' => true, 'field_order' => 2],
    ];

    Http::fake([
        '*/api/chat' => Http::response(ollamaReply(json_encode($kept))),
    ]);

    $result = makeLlm()->refineFields(candidates());

    expect(array_column($result, 'field_key'))->toBe(['student_name', 'age']);
    expect($result[1]['field_type'])->toBe('number');
    expect(array_column($result, 'field_order'))->toBe([1, 2]);
});

it('falls back to the input candidates on invalid JSON', function () {
    Http::fake([
        '*/api/chat' => Http::response(ollamaReply('not json at all')),
    ]);

    $input = candidates();
    $result = makeLlm()->refineFields($input);

    expect(array_column($result, 'field_key'))->toBe(array_column($input, 'field_key'));
});

it('falls back to the input candidates on an empty response', function () {
    Http::fake([
        '*/api/chat' => Http::response(ollamaReply('')),
    ]);

    $input = candidates();
    $result = makeLlm()->refineFields($input);

    expect(array_column($result, 'field_key'))->toBe(array_column($input, 'field_key'));
});

it('discards model output that invents a new key', function () {
    $withInvented = [
        ['label' => 'Student Name', 'field_key' => 'student_name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
        ['label' => 'Made Up', 'field_key' => 'made_up', 'field_type' => 'text', 'is_required' => true, 'field_order' => 2],
    ];

    Http::fake([
        '*/api/chat' => Http::response(ollamaReply(json_encode($withInvented))),
    ]);

    $input = candidates();
    $result = makeLlm()->refineFields($input);

    // Invalid (not a subset) → falls back to the full input unchanged.
    expect(array_column($result, 'field_key'))->toBe(array_column($input, 'field_key'));
});

it('clamps an out-of-enum field_type to text', function () {
    $badType = [
        ['label' => 'Student Name', 'field_key' => 'student_name', 'field_type' => 'wizardry', 'is_required' => true, 'field_order' => 1],
    ];

    Http::fake([
        '*/api/chat' => Http::response(ollamaReply(json_encode($badType))),
    ]);

    $result = makeLlm()->refineFields([
        ['label' => 'Student Name', 'field_key' => 'student_name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
    ]);

    expect($result[0]['field_type'])->toBe('text');
});

it('sends num_ctx and keep_alive in the request body', function () {
    Http::fake([
        '*/api/chat' => Http::response(ollamaReply('[]')),
    ]);

    makeLlm()->refineFields(candidates());

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['keep_alive'] ?? null) === '10m'
            && (($body['options']['num_ctx'] ?? null) === 8192);
    });
});
