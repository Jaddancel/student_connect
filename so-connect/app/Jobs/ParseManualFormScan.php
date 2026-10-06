<?php

namespace App\Jobs;

use App\Models\ManualFormSession;
use App\Services\ManualForm\ManualScanParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Parses a returned handwritten scan against a session's frozen reference pages
 * and moves the session to `review` (or a retryable `failed` state). Idempotent:
 * it no-ops unless the session is currently `parsing`.
 */
class ParseManualFormScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $sessionId, public readonly ?int $documentId = null) {}

    public function handle(ManualScanParser $parser): void
    {
        $parser->parse($this->sessionId, $this->documentId);
    }

    public function failed(?\Throwable $throwable): void
    {
        if ($this->documentId !== null) {
            $document = \App\Models\ManualFormSessionDocument::query()
                ->where('manual_form_session_id', $this->sessionId)->find($this->documentId);
            if ($document?->status === ManualFormSession::STATUS_PARSING) {
                $document->update(['status' => ManualFormSession::STATUS_FAILED,
                    'parse_error' => 'The scan could not be read in time. Please retry the saved draft.']);
                app(\App\Services\ManualForm\ManualFormSessionService::class)->syncDocuments($document->session);
            }

            return;
        }
        ManualFormSession::query()
            ->whereKey($this->sessionId)
            ->where('status', ManualFormSession::STATUS_PARSING)
            ->update([
                'status' => ManualFormSession::STATUS_FAILED,
                'parse_error' => 'The scan could not be read in time. Please retry the saved draft.',
            ]);
    }
}
