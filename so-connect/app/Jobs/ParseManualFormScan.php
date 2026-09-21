<?php

namespace App\Jobs;

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

    public function __construct(public readonly string $sessionId) {}

    public function handle(ManualScanParser $parser): void
    {
        $parser->parse($this->sessionId);
    }
}
