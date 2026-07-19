<?php

namespace App\Console\Commands;

use App\Services\SignatureReferenceService;
use Illuminate\Console\Command;

/**
 * One-off backfill of the signature reference registry from existing profile
 * signatures, so the verifier has candidates on installs that predate the
 * registry.
 */
class BackfillSignatureReferences extends Command
{
    protected $signature = 'signatures:backfill-references';

    protected $description = 'Populate the signature reference registry from existing profile signatures';

    public function handle(SignatureReferenceService $service): int
    {
        $count = $service->backfill();
        $this->info("Synced {$count} profile signature(s) into the registry.");

        return self::SUCCESS;
    }
}
