<?php

namespace App\Console\Commands;

use App\Mail\AfterEventReportDueMail;
use App\Services\AfterEventReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every official of an organization once one of its events is due an
 * after-event report (the admin-set number of days after it ended) and the
 * report is still unfiled. Each event is emailed once; the bell keeps
 * reminding until the report is filed.
 */
class NotifyAfterEventReports extends Command
{
    protected $signature = 'after-event:notify {--dry-run : List the events that would be emailed}';

    protected $description = 'Email organization officials about due, unfiled after-event reports';

    public function handle(AfterEventReportService $afterEvents): int
    {
        if (! $afterEvents->enabled()) {
            $this->info('The After Event Report function has no published form; nothing to do.');

            return self::SUCCESS;
        }

        $organizationIds = DB::table('events')->whereNotNull('organization')->distinct()->pluck('organization')
            ->map(fn ($id) => (int) $id)->all();

        $alreadyNotified = DB::table('after_event_report_notifications')->pluck('event_id')
            ->map(fn ($id) => (int) $id)->flip();

        $due = $afterEvents->unfiledEvents($organizationIds)
            ->reject(fn (array $event) => $alreadyNotified->has($event['event_id']));

        $sent = 0;
        foreach ($due as $event) {
            if ($this->option('dry-run')) {
                $this->line('Would notify: #'.$event['event_id'].' '.$event['name'].' ('.$event['organization_name'].')');

                continue;
            }

            $recipients = 0;
            foreach ($afterEvents->officials($event['organization_id']) as $official) {
                $email = trim((string) $official->user_email);
                if ($email === '') {
                    continue;
                }

                try {
                    Mail::to($email)->send(new AfterEventReportDueMail(
                        $this->displayName($official),
                        $event['name'],
                        $event['organization_name'],
                        $event['finished_at']->format('F j, Y g:i A'),
                        $event['deadline']?->format('F j, Y'),
                        route('after-event-reports.index'),
                    ));
                    $recipients++;
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }

            DB::table('after_event_report_notifications')->insert([
                'event_id' => $event['event_id'],
                'recipients' => $recipients,
                'notified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sent++;
        }

        $this->info(($this->option('dry-run') ? 'Due' : 'Notified').' events: '.($this->option('dry-run') ? $due->count() : $sent));

        return self::SUCCESS;
    }

    private function displayName(\App\Models\User $user): string
    {
        $profile = $user->profile()->first();
        $name = trim(implode(' ', array_filter([$profile?->first_name, $profile?->last_name])));

        return $name !== '' ? $name : (string) $user->user_email;
    }
}
