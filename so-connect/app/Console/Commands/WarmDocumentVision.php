<?php

namespace App\Console\Commands;

use App\Services\DocumentVision\DocumentVisionClient;
use App\Services\Scanning\VisionZoneReader;
use Illuminate\Console\Command;

/**
 * Keeps the document-vision model loaded on the GPU so live ID and waiver
 * scans never wait for a cold load (which can take minutes). Runs every few
 * minutes from the scheduler; a no-op when the model is already loaded, so it
 * mostly matters after the Ollama container restarts.
 */
class WarmDocumentVision extends Command
{
    protected $signature = 'vision:warm';

    protected $description = 'Load the document-vision model into memory if it is not already loaded';

    public function handle(DocumentVisionClient $vision): int
    {
        if (! VisionZoneReader::enabled()) {
            $this->line('Vision zone reading is disabled; nothing to warm.');

            return self::SUCCESS;
        }

        if ($vision->warm()) {
            $this->info('Document-vision model is loaded.');

            return self::SUCCESS;
        }

        $this->warn('Could not load the document-vision model (is Ollama running and the model pulled?).');

        return self::FAILURE;
    }
}
