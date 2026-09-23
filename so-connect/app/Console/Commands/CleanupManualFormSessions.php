<?php

namespace App\Console\Commands;

use App\Models\ManualFormSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Reaps abandoned manual-filling drafts and their files. A draft that is still
 * open (never submitted) past its 30-day expiry — or already marked expired —
 * is deleted along with its frozen PDF, rasterized pages, uploads, and scans.
 *
 * Submitted sessions are never touched here: their files follow the finalized
 * submission's own lifecycle.
 */
class CleanupManualFormSessions extends Command
{
    protected $signature = 'manual-sessions:cleanup {--dry-run : List what would be removed without deleting}';

    protected $description = 'Delete expired, abandoned manual-filling drafts and their files';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = (string) config('documents.disk', 'public');

        $stale = ManualFormSession::query()
            ->whereIn('status', ManualFormSession::OPEN_STATUSES)
            ->where('expires_at', '<', now())
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No expired manual drafts to clean up.');

            return self::SUCCESS;
        }

        foreach ($stale as $session) {
            $dir = 'manual-form/'.$session->getKey();

            if ($dryRun) {
                $this->line('would remove draft '.$session->getKey().' ('.$session->status.')');

                continue;
            }

            try {
                Storage::disk($disk)->deleteDirectory($dir);
                File::deleteDirectory(Storage::disk($disk)->path($dir));
            } catch (\Throwable $e) {
                report($e);
            }

            $session->forceFill(['status' => ManualFormSession::STATUS_EXPIRED])->save();
            $session->delete();
        }

        $this->info(($dryRun ? 'Would delete ' : 'Deleted ').$stale->count().' expired manual draft(s).');

        return self::SUCCESS;
    }
}
