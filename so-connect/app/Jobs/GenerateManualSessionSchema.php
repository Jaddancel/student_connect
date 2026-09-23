<?php

namespace App\Jobs;

use App\Services\ManualForm\ManualSessionSchemaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Builds a manual-filling session's parsing schema from its frozen partial PDF
 * and flips it to `awaiting_scan` (or `failed` when a required field has no
 * writable area). Idempotent: it no-ops unless the session is still preparing.
 */
class GenerateManualSessionSchema implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $sessionId) {}

    public function handle(ManualSessionSchemaGenerator $generator): void
    {
        $generator->generate($this->sessionId);
    }
}
