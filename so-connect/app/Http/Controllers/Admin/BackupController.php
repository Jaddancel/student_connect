<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActionLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Super-admin database backup management: list / create / download / delete
 * backups and restore the database from one. All actions are audited; restore
 * is destructive (replaces the current DB from the chosen dump, after a safety
 * backup).
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('pages.admin.backups.index', [
            'title' => 'Database Backups',
            'backups' => $this->backups->list(),
            'archivedCount' => count($this->backups->listArchived()),
            'intervalHours' => (int) \App\Models\AppSetting::get('backup.interval_hours', 24),
        ]);
    }

    public function archived(): View
    {
        return view('pages.admin.backups.archived', [
            'title' => 'Archived Backups',
            'archived' => $this->backups->listArchived(),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $this->backups->create();
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => 'Backup failed: '.$e->getMessage()]);
        }

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_created', 'Created a database backup');

        return back()->with('success', 'Backup created.');
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

    public function restore(string $filename): RedirectResponse
    {
        try {
            $safety = $this->backups->restore($filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => 'Restore failed: '.$e->getMessage()]);
        }

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_restored', 'Restored the database from '.$filename, ['file' => $filename]);

        // The safety snapshot shows up as a brand-new archive in the list; say so
        // explicitly, otherwise it reads as a backup nobody asked for.
        $message = 'Database restored from '.$filename.'.';
        $message .= $safety
            ? ' A safety backup of the previous state was saved as '.$safety.'.'
            : ' A safety backup of the previous state was saved first.';

        return back()->with('success', $message);
    }
}
