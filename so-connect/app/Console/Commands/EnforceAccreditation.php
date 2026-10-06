<?php

namespace App\Console\Commands;

use App\Services\AccreditationService;
use Illuminate\Console\Command;

/**
 * Enforces the organization-accreditation cycle. Reports the current standing
 * and, when asked, disables non-compliant orgs past the deadline and hard-purges
 * disabled orgs past their grace period.
 *
 * Destructive steps are opt-in: a bare run (and --dry-run) only reports; the
 * daily schedule runs with --disable (never --purge) so purging stays a
 * deliberate act (this command with --purge, or the super-admin restore UI).
 */
class EnforceAccreditation extends Command
{
    protected $signature = 'accreditation:enforce
        {--disable : Disable non-compliant orgs once the deadline has passed}
        {--purge : Hard-delete disabled orgs whose grace period has elapsed}
        {--dry-run : Report only; make no changes}';

    protected $description = 'Report and enforce organization accreditation (disable / purge)';

    public function handle(AccreditationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deadline = $service->deadline();

        $this->line('Accreditation deadline: '.($deadline?->toDateString() ?? 'none scheduled'));
        if ($deadline) {
            $this->line('Days until deadline: '.$service->daysUntilDeadline());
        }

        $toDisable = $service->orgsToDisable();
        $toPurge = $service->orgsToPurge();
        $this->line('Non-compliant orgs past deadline: '.$toDisable->count());
        $this->line('Disabled orgs past grace (purge-eligible): '.$toPurge->count());

        if ($dryRun) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if ($this->option('disable')) {
            foreach ($toDisable as $org) {
                $service->disable($org);
            }
            $this->info('Disabled '.$toDisable->count().' organization(s).');
        }

        if ($this->option('purge')) {
            $purged = 0;
            foreach ($toPurge as $org) {
                $service->purge($org);
                $purged++;
            }
            $this->info('Purged '.$purged.' organization(s).');
        }

        if (! $this->option('disable') && ! $this->option('purge')) {
            $this->comment('No action flags given (--disable / --purge). Nothing changed.');
        }

        return self::SUCCESS;
    }
}
