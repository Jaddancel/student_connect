<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Runs a database backup only if the admin-configured interval
 * (backup.interval_hours) has elapsed since the most recent backup. Scheduled
 * hourly, it becomes a no-op between due times, so the effective cadence is the
 * configured interval.
 */
class AutoBackup extends Command
{
    protected $signature = 'backup:auto {--force : Back up now regardless of the interval}';

    protected $description = 'Run a DB backup when the configured interval has elapsed';

    public function handle(BackupService $backups): int
    {
        $intervalHours = max(1, (int) AppSetting::get('backup.interval_hours', 24));
        $latest = $backups->list()[0]['last_modified'] ?? null;

        if (! $this->option('force') && $latest !== null) {
            $elapsedHours = (now()->timestamp - $latest) / 3600;
            if ($elapsedHours < $intervalHours) {
                $this->info('Most recent backup is '.round($elapsedHours, 1).'h old (< '.$intervalHours.'h) — skipping.');

                return self::SUCCESS;
            }
        }

        $backups->create();
        $this->info('Backup created.');

        return self::SUCCESS;
    }
}
