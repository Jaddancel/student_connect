<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for Google's Gemini API powering the in-app assistant.
 *
 * Failure is always graceful: missing configuration, a timeout, non-200, or
 * an unusable response yields `ok: false`, never an exception in the request
 * flow. The API key stays server-side and is never logged.
 */
class GeminiClient
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     * @return array{ok: bool, reply: string, note?: string}
     */
    public function chat(array $messages, string $systemPrompt): array
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        if ($apiKey === '') {
            Log::warning('Gemini API key is not configured');

            return ['ok' => false, 'reply' => '', 'note' => 'assistant unavailable'];
        }

        $baseUrl = rtrim((string) config('services.gemini.url'), '/');
        $model = (string) config('services.gemini.model');
        $timeout = (int) config('services.gemini.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post($baseUrl.'/models/'.rawurlencode($model).':generateContent', [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => array_map(fn (array $message) => [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]],
                    ], $messages),
                    'generationConfig' => [
                        'candidateCount' => 1,
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini API returned non-200', ['status' => $response->status()]);

                return ['ok' => false, 'reply' => '', 'note' => 'assistant error'];
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $reply = is_array($parts)
                ? collect($parts)->pluck('text')->filter(fn ($text) => is_string($text))->implode('')
                : '';

            if ($reply === '') {
                Log::warning('Gemini API returned no usable text response');

                return ['ok' => false, 'reply' => '', 'note' => 'assistant error'];
            }

            return ['ok' => true, 'reply' => $reply];
        } catch (\Throwable $exception) {
            Log::warning('Gemini API unreachable: '.$exception->getMessage());

            return ['ok' => false, 'reply' => '', 'note' => 'assistant unavailable'];
        }
    }
}
