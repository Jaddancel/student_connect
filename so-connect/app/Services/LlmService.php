<?php

namespace App\Services;

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
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
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

        return is_array($decoded) ? $decoded : [];
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
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/api/chat', array_merge([
                'model' => $this->model,
                'stream' => false,
                'messages' => $messages,
            ], $extra));

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
}
