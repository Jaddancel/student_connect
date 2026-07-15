<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes every builder form and its dependent data: generated documents
 * (rows AND their files), submissions, field definitions, and the form rows
 * themselves. Scoring rules that referenced a deleted form are disabled so the
 * rule editor can flag them instead of silently evaluating against nothing.
 *
 * Destructive by design (the seeded demo/legacy forms are retired in favor of
 * admin-authored form pages) — hence the required --force flag.
 */
class WipeForms extends Command
{
    protected $signature = 'forms:wipe {--force : Actually delete; without it the command only reports}';

    protected $description = 'Delete ALL forms, their fields, submissions and generated documents';

    public function handle(): int
    {
        $counts = [
            'forms' => DB::table('forms')->count(),
            'form_descriptions' => DB::table('form_descriptions')->count(),
            'form_submissions' => DB::table('form_submissions')->count(),
            'generated_documents' => DB::table('generated_documents')->count(),
        ];

        foreach ($counts as $table => $count) {
            $this->line(sprintf('%-20s %d row(s)', $table, $count));
        }

        if (! $this->option('force')) {
            $this->warn('Dry run — re-run with --force to delete everything listed above.');

            return self::SUCCESS;
        }

        $disk = Storage::disk((string) config('documents.disk', 'public'));

        // Collect generated files before the rows cascade away.
        $files = DB::table('generated_documents')
            ->get(['docx_path', 'pdf_path'])
            ->flatMap(fn ($doc) => [$doc->docx_path, $doc->pdf_path])
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () {
            // generated_documents cascades from form_submissions; delete
            // explicitly anyway so the count is honest even if the FK changes.
            DB::table('generated_documents')->delete();
            DB::table('form_submissions')->delete();
            DB::table('form_descriptions')->delete();

            // Scoring rules triggered by a form can no longer fire once the
            // form is gone; disable them so the editor surfaces the gap.
            if (Schema::hasTable('scoring_rules')) {
                DB::table('scoring_rules')->update(['enabled' => false]);
            }

            // Per-form request types (system_key form:<id>) lose their form;
            // deactivate rather than delete — historical requests reference
            // them and the Request Records page still reads their names.
            DB::table('request_types')
                ->where('system_key', 'like', \App\Services\RequestTypeService::FORM_KEY_PREFIX.'%')
                ->update(['is_active' => false]);

            DB::table('forms')->delete();
        });

        if ($files !== []) {
            $disk->delete($files);
        }

        $this->info(sprintf(
            'Deleted %d form(s), %d field(s), %d submission(s), %d generated document(s) (+%d file(s)).',
            $counts['forms'],
            $counts['form_descriptions'],
            $counts['form_submissions'],
            $counts['generated_documents'],
            count($files),
        ));

        return self::SUCCESS;
    }
}
