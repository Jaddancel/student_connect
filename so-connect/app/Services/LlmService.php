<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the local Ollama LLM sidecar.
 *
 * Text-only duties:
 *   - interpretFields(): turn OCR'd DOCX text into structured form-field JSON.
 *   - chat(): general conversational endpoint (chatbot building block).
 *
 * The LLM never receives images — PaddleOCR (OcrService) handles all image work.
 */
class LlmService
{
    /** Field-type enum the wizard accepts; refinement clamps to this set. */
    private const FIELD_TYPES = ['text', 'textarea', 'checkbox', 'date', 'number', 'email', 'signature', 'repeating'];

    /** Refine at most this many candidates per request to stay within context. */
    private const REFINE_CHUNK_SIZE = 25;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
        private readonly int $numCtx = 8192,
        private readonly string $keepAlive = '10m',
    ) {
    }

    /**
     * Interpret extracted document text into a list of form-field definitions.
     *
     * Returns an empty array on any failure so callers can degrade gracefully
     * to a manual field-entry table rather than erroring.
     *
     * @return array<int, array{label: string, field_key: string, field_type: string, is_required: bool, field_order: int}>
     */
    public function interpretFields(string $extractedText): array
    {
        try {
            $content = $this->send(
                [
                    ['role' => 'system', 'content' => 'You are a form field extractor. Return ONLY a valid JSON array, no explanation, no markdown.'],
                    ['role' => 'user', 'content' => $this->buildFieldPrompt($extractedText)],
                ],
                // Deterministic output. NOTE: we deliberately do NOT set Ollama's
                // 'format' => 'json' — its grammar-constrained sampling biases small
                // models (phi4-mini) into emitting a single object and stopping,
                // collapsing a multi-field form to one field. Free generation with the
                // few-shot prompt yields the full array; decodeJsonArray() strips any
                // ```json fences the model adds.
                ['options' => ['temperature' => 0]],
            );
        } catch (\Throwable) {
            return [];
        }

        $decoded = $this->decodeJsonArray($content);

        return is_array($decoded) ? $this->normalizeFields($decoded) : [];
    }

    /**
     * Best-effort refinement of a deterministic candidate field list.
     *
     * The model may only (a) drop entries that are not user-input fields and
     * (b) correct an obviously wrong field_type. It may NOT add fields or rename
     * keys. The result must be a non-empty subset whose keys all exist in the
     * input; otherwise we return the original candidates unchanged. This makes
     * the LLM purely subtractive — it can never make detection worse than the
     * deterministic floor.
     *
     * @param  array<int, array{label: string, field_key: string, field_type: string, is_required: bool, field_order: int}>  $candidates
     * @return array<int, array{label: string, field_key: string, field_type: string, is_required: bool, field_order: int}>
     */
    public function refineFields(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $refined = [];

        foreach (array_chunk($candidates, self::REFINE_CHUNK_SIZE) as $chunk) {
            $refined = array_merge($refined, $this->refineChunk($chunk));
        }

        // Renumber field_order across the merged chunks.
        foreach ($refined as $i => &$field) {
            $field['field_order'] = $i + 1;
        }

        return $refined;
    }

    /**
     * Refine a single chunk, falling back to the chunk unchanged on any failure.
     *
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, array<string, mixed>>
     */
    private function refineChunk(array $chunk): array
    {
        $allowedKeys = array_column($chunk, 'field_key');

        foreach ([false, true] as $terse) {
            try {
                $content = $this->send([
                    ['role' => 'system', 'content' => 'You clean up a list of form fields. Return ONLY a valid JSON array, no explanation, no markdown.'],
                    ['role' => 'user', 'content' => $this->buildRefinementPrompt($chunk, $terse)],
                ], ['options' => ['temperature' => 0]]);
            } catch (\Throwable) {
                return $chunk;
            }

            $decoded = $this->decodeJsonArray($content);
            if (! is_array($decoded)) {
                continue;
            }

            $normalized = $this->normalizeFields($decoded);

            // Must be a non-empty subset of the input (no invented keys).
            $valid = $normalized !== []
                && array_reduce(
                    $normalized,
                    fn (bool $carry, array $f) => $carry && in_array($f['field_key'], $allowedKeys, true),
                    true,
                );

            if ($valid) {
                return $normalized;
            }
        }

        return $chunk;
    }

    private function buildRefinementPrompt(array $chunk, bool $terse): string
    {
        $json = json_encode(array_values($chunk), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $types = implode(', ', self::FIELD_TYPES);

        if ($terse) {
            return "Return ONLY the JSON array. Allowed field_type values: {$types}.\n{$json}";
        }

        return <<<PROMPT
        Below is a JSON array of form fields detected from a document. Clean it up:
        - REMOVE any entry that is not something a person fills in (e.g. a heading,
          instruction, or office-use-only line that slipped through).
        - FIX field_type if it is clearly wrong. Allowed values: {$types}.
        - Do NOT add new fields. Do NOT change "field_key" or "label". Keep the rest.

        Return ONLY the resulting JSON array (no prose, no markdown).

        {$json}
        PROMPT;
    }

    /**
     * General chat completion. Returns the assistant message content (or '').
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        try {
            return $this->send($messages);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * POST a non-streaming chat request and return message.content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $extra  Extra Ollama params, e.g. ['format' => 'json', 'options' => ['temperature' => 0]]
     */
    private function send(array $messages, array $extra = []): string
    {
        // Merge our context-window default under any caller-supplied options so
        // long forms aren't silently truncated; keep_alive keeps the model warm
        // between requests to avoid cold-start loads blowing the HTTP timeout.
        $options = array_merge(['num_ctx' => $this->numCtx], $extra['options'] ?? []);
        unset($extra['options']);

        $payload = array_merge([
            'model' => $this->model,
            'stream' => false,
            'keep_alive' => $this->keepAlive,
            'messages' => $messages,
            'options' => $options,
        ], $extra);

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/api/chat', $payload);

        $response->throw();

        return (string) $response->json('message.content', '');
    }

    private function buildFieldPrompt(string $extractedText): string
    {
        return <<<PROMPT
        You are given the full text of a fillable form document (a Word document, read in reading order).
        Identify every input field a person must fill in.

        Layout: lines are in reading order. Within a line, " | " separates cells that sit
        side-by-side in the SAME table row (adjacent columns). Each such cell is usually its OWN
        field. Example: "First Name | Middle Name | Last Name" is THREE fields on one row.
        A column-header row like "Freshman | Sophomore | Junior | Total" gives the labels for the
        inputs in that table. Fields on their own line (no " | ") are stacked vertically.

        What counts as a field (be generous — most labels are fields):
        - A label followed by a colon, blank, or underscores (e.g. "Name:", "Date Filed _____").
        - Any cell in a form table that names something to fill in (column headers and row labels both count).
        - A short noun-phrase label even without a colon (e.g. "Email Address").
        Only EXCLUDE: the document's main title, long instruction/legal/boilerplate sentences, and
        signature/approval lines that office staff (not the applicant) sign.
        When unsure, include it as a field.

        For each field, infer "field_type" from the label's meaning:
        - "date"     -> dates (date of birth, date filed, effective date)
        - "email"    -> email address
        - "number"   -> counts, quantities, amounts, ages, years
        - "textarea" -> long free text (objectives, description, remarks, justification, address)
        - "checkbox" -> yes/no or single check options
        - "signature"-> a line where the APPLICANT signs (e.g. "Signature of Applicant", "Signed by")
        - "repeating"-> a table/list the applicant fills with multiple rows (e.g. "List of Activities", "Members", itemised expenses)
        - "text"     -> everything else (names, titles, short single-line answers)

        Rules:
        - "field_key": snake_case derived from the label (e.g. "Student Name" -> "student_name"). Unique. No spaces or punctuation.
        - "is_required": true unless the label clearly marks it optional.
        - "field_order": 1-based, following the order fields appear in the text.
        - Return ONLY a JSON array (no wrapping object, no prose, no markdown).

        Example input:
        Date Filed:
        First Name | Middle Name | Last Name
        Email Address:
        Objectives

        Example output:
        [
          {"label": "Date Filed", "field_key": "date_filed", "field_type": "date", "is_required": true, "field_order": 1},
          {"label": "First Name", "field_key": "first_name", "field_type": "text", "is_required": true, "field_order": 2},
          {"label": "Middle Name", "field_key": "middle_name", "field_type": "text", "is_required": false, "field_order": 3},
          {"label": "Last Name", "field_key": "last_name", "field_type": "text", "is_required": true, "field_order": 4},
          {"label": "Email Address", "field_key": "email_address", "field_type": "email", "is_required": true, "field_order": 5},
          {"label": "Objectives", "field_key": "objectives", "field_type": "textarea", "is_required": false, "field_order": 6}
        ]

        Form document text:
        ---
        {$extractedText}
        ---
        PROMPT;
    }

    /**
     * Decode an LLM response into an array, tolerating stray prose or ```json fences.
     */
    private function decodeJsonArray(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        // Strip markdown code fences if the model added them despite instructions.
        if (Str::startsWith($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', (string) $content);
            $content = trim((string) $content);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $this->unwrapFieldArray($decoded);
        }

        // Last resort: extract the first JSON array substring.
        if (preg_match('/\[.*\]/s', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $this->unwrapFieldArray($decoded) : null;
        }

        return null;
    }

    /**
     * Normalises the decoded payload to a list of field objects. Tolerates a
     * model that wraps the array under a key (e.g. {"fields": [...]}) despite
     * being told to return a bare array.
     */
    private function unwrapFieldArray(array $decoded): array
    {
        // Already a list of field objects.
        if (array_is_list($decoded)) {
            return $decoded;
        }

        // Common wrapper keys a small model might use.
        foreach (['fields', 'form_fields', 'data', 'items'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return array_is_list($decoded[$key]) ? $decoded[$key] : [$decoded[$key]];
            }
        }

        // A single field object returned bare.
        if (isset($decoded['field_key']) || isset($decoded['label'])) {
            return [$decoded];
        }

        return [];
    }

    /**
     * Coerce a raw decoded payload into valid, deduplicated field objects.
     * Single choke-point that guarantees well-formed output regardless of model
     * behaviour: required keys present, snake_case keys, enum-clamped types,
     * sequential order, no empty labels, no duplicate keys.
     *
     * @return array<int, array{label: string, field_key: string, field_type: string, is_required: bool, field_order: int}>
     */
    private function normalizeFields(array $raw): array
    {
        $fields = [];
        $seenKeys = [];
        $order = 0;

        foreach ($this->unwrapFieldArray($raw) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? $entry['field_label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $rawKey = (string) ($entry['field_key'] ?? $label);
            $baseKey = FormTemplateHelper::normalizeFieldKey($rawKey);

            if (isset($seenKeys[$baseKey])) {
                $seenKeys[$baseKey]++;
                $key = $baseKey.'_'.$seenKeys[$baseKey];
            } else {
                $seenKeys[$baseKey] = 1;
                $key = $baseKey;
            }

            $type = (string) ($entry['field_type'] ?? 'text');
            if (! in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }

            $fields[] = [
                'label' => $label,
                'field_key' => $key,
                'field_type' => $type,
                'is_required' => (bool) ($entry['is_required'] ?? true),
                'field_order' => ++$order,
            ];
        }

        return $fields;
    }
}
