<?php

namespace App\Jobs;

use App\Models\ManualFormSession;
use App\Services\ManualForm\ManualFormSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareManualFormSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $sessionId) {}

    public function handle(ManualFormSessionService $sessions): void
    {
        $sessions->prepare($this->sessionId);
    }

    public function failed(?\Throwable $throwable): void
    {
        ManualFormSession::query()
            ->whereKey($this->sessionId)
            ->where('status', ManualFormSession::STATUS_PREPARING)
            ->update([
                'status' => ManualFormSession::STATUS_FAILED,
                'parse_error' => 'Could not prepare the printable form. Please start a new manual-filling draft.',
            ]);
    }
}
