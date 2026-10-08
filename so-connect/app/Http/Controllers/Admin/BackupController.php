<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActionLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Super-admin database backup management: list / create / download / delete
 * backups and restore the database from a stored or uploaded one. All actions
 * are audited; restore
 * is destructive (replaces the current DB from the chosen dump, after a safety
 * backup).
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('pages.admin.backups.index', [
            'title' => 'Backups',
            'backups' => $this->backups->list(),
            'archivedCount' => count($this->backups->listArchived()),
        ]);
    }

    public function archived(): View
    {
        return view('pages.admin.backups.archived', [
            'title' => 'Archived Backups',
            'archived' => $this->backups->listArchived(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->validate([
            'type' => ['sometimes', Rule::in(BackupService::TYPES)],
        ])['type'] ?? BackupService::TYPE_DATABASE;
        $config = $type === BackupService::TYPE_CONFIGURATION;

        try {
            $name = $this->backups->create($type);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Backup failed: '.$e->getMessage()]);
        }

        ActionLogger::log(
            ActionLogger::CATEGORY_SETTINGS,
            $config ? 'config_backup_created' : 'backup_created',
            $config ? 'Created a configuration backup' : 'Created a database backup',
            array_filter(['file' => $name, 'type' => $type]),
        );

        return back()->with('success', $config ? 'Configuration backup created.' : 'Database backup created.');
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        return Response::download($this->backups->path($filename, $request->boolean('archived')), $filename);
    }

    public function archive(string $filename): RedirectResponse
    {
        try {
            $this->backups->archive($filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Archive failed: '.$e->getMessage()]);
        }

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_archived', 'Archived backup '.$filename, ['file' => $filename]);

        return back()->with('success', 'Backup archived.');
    }

    public function unarchive(string $filename): RedirectResponse
    {
        try {
            $this->backups->unarchive($filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Unarchive failed: '.$e->getMessage()]);
        }

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_unarchived', 'Unarchived backup '.$filename, ['file' => $filename]);

        return back()->with('success', 'Backup restored to the active list.');
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        $this->backups->delete($filename, $request->boolean('archived'));

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_deleted', 'Deleted backup '.$filename, ['file' => $filename]);

        return back()->with('success', 'Backup deleted.');
    }

    /**
     * Archive or delete several active backups at once. Each file is audited
     * individually, under the same action names as the single-file operations.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:archive,delete'],
            'filenames' => ['required', 'array', 'min:1'],
            'filenames.*' => ['required', 'string'],
        ], [
            'filenames.required' => 'Select at least one backup.',
        ]);

        $archive = $data['action'] === 'archive';
        $done = 0;
        $failed = [];

        foreach (array_unique(array_map('basename', $data['filenames'])) as $filename) {
            try {
                $archive ? $this->backups->archive($filename) : $this->backups->delete($filename);
            } catch (\Throwable $e) {
                $failed[] = $filename;

                continue;
            }

            $done++;
            $archive
                ? ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_archived', 'Archived backup '.$filename, ['file' => $filename, 'bulk' => true])
                : ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_deleted', 'Deleted backup '.$filename, ['file' => $filename, 'bulk' => true]);
        }

        $response = back();
        if ($done > 0) {
            $response->with('success', ($archive ? 'Archived ' : 'Deleted ').$done.' backup'.($done === 1 ? '' : 's').'.');
        }
        if ($failed) {
            $response->withErrors(['backup' => 'Could not '.$data['action'].': '.implode(', ', $failed).'.']);
        }

        return $response;
    }

    public function restore(string $filename): RedirectResponse
    {
        try {
            $result = $this->backups->restore($filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => 'Restore failed: '.$e->getMessage()]);
        }

        return $this->restored($result, $filename, $filename);
    }

    /**
     * Restore from a backup archive uploaded by the user (e.g. one downloaded
     * from this page earlier, or from another environment).
     */
    public function restoreUpload(Request $request): RedirectResponse
    {
        $request->validate([
            // 100 MB, matching the container's upload_max_filesize.
            'backup_file' => ['required', 'file', 'extensions:zip', 'max:102400'],
        ], [
            'backup_file.extensions' => 'The backup file must be a .zip archive downloaded from this page.',
        ]);

        $file = $request->file('backup_file');
        $name = basename($file->getClientOriginalName());

        try {
            $result = $this->backups->restoreFromUpload($file);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => 'Restore failed: '.$e->getMessage()]);
        }

        return $this->restored($result, 'uploaded file '.$name, $name, upload: true);
    }

    /**
     * Audit a finished restore and flash what happened.
     *
     * @param  array{type:string, safety:?string, summary:?array}  $result
     */
    private function restored(array $result, string $source, string $file, bool $upload = false): RedirectResponse
    {
        $config = $result['type'] === BackupService::TYPE_CONFIGURATION;

        ActionLogger::log(
            ActionLogger::CATEGORY_SETTINGS,
            $config ? 'config_backup_restored' : 'backup_restored',
            ($config ? 'Merged the configuration from ' : 'Restored the database from ').$source,
            array_filter(['file' => $file, 'source' => $upload ? 'upload' : null, 'type' => $result['type'], 'summary' => $result['summary']]),
        );

        // The safety snapshot shows up as a brand-new archive in the list; say so
        // explicitly, otherwise it reads as a backup nobody asked for.
        $safety = $result['safety']
            ? ' A safety backup of the previous state was saved as '.$result['safety'].'.'
            : ' A safety backup of the previous state was saved first.';

        if (! $config) {
            return back()->with('success', 'Database restored from '.$source.'.'.$safety);
        }

        $summary = (array) $result['summary'];
        $response = back()->with('success', 'Configuration merged from '.$source.': '.self::describeCounts($summary).'.'.$safety);

        return ($summary['warnings'] ?? []) !== []
            ? $response->with('restore_warnings', $summary['warnings'])
            : $response;
    }

    /**
     * @param  array{created?: array<string,int>, updated?: array<string,int>}  $summary
     */
    private static function describeCounts(array $summary): string
    {
        $labels = [
            'settings' => 'setting', 'forms' => 'form', 'fields' => 'field', 'templates' => 'printed template',
            'report_templates' => 'report', 'waiver_templates' => 'waiver template',
            'scoring_categories' => 'tally category',
            'scoring_criteria' => 'tally criterion', 'scoring_rules' => 'tally rule',
        ];

        $parts = [];
        foreach (['created', 'updated'] as $bucket) {
            $items = [];
            foreach ($labels as $key => $label) {
                $n = (int) ($summary[$bucket][$key] ?? 0);
                if ($n > 0) {
                    $items[] = $n.' '.($n === 1 ? $label : ($label === 'tally criterion' ? 'tally criteria' : $label.'s'));
                }
            }
            if ($items !== []) {
                $parts[] = $bucket.' '.implode(', ', $items);
            }
        }

        return $parts === [] ? 'nothing to change' : implode('; ', $parts);
    }
}
