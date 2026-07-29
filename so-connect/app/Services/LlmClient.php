<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Ollama sidecar (docker/ollama) powering the in-app
 * assistant. Mirrors {@see OcrClient}: no constructor, config read inline per
 * method, injected as a controller method parameter.
 *
 * Failure is always graceful: a timeout, non-200, or unreachable sidecar
 * yields `ok: false` (never an exception into the request flow). See
 * docs/assistant-contract.md for the full wire contract.
 */
class LlmClient
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages  conversation turns (user/assistant only — the system prompt is prepended here)
     * @return array{ok: bool, reply: string, note?: string}
     */
    public function chat(array $messages, string $systemPrompt): array
    {
        $url = rtrim((string) config('services.llm.url'), '/').'/api/chat';
        $timeout = (int) config('services.llm.timeout', 120);
        $model = (string) config('services.llm.model');

        try {
            $response = Http::timeout($timeout)->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ...$messages,
                ],
                'stream' => false,
                // Qwen3.5's hybrid reasoning mode burns 2-5x the tokens on
                // thinking traces the UI never shows — load-bearing on an
                // 8 GB card where every second of latency is felt.
                'think' => false,
                'options' => [
                    'temperature' => 0.3,
                    'num_ctx' => 8192,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('LLM sidecar returned non-200', ['status' => $response->status()]);

                return ['ok' => false, 'reply' => '', 'note' => 'assistant error'];
            }

            return ['ok' => true, 'reply' => (string) $response->json('message.content', '')];
        } catch (\Throwable $e) {
            Log::warning('LLM unreachable: '.$e->getMessage());

            return ['ok' => false, 'reply' => '', 'note' => 'assistant unavailable'];
        }
    }
}
