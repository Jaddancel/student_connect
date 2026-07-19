<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActionLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
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
            'intervalHours' => (int) \App\Models\AppSetting::get('backup.interval_hours', 24),
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

    public function download(string $filename): BinaryFileResponse
    {
        return Response::download($this->backups->path($filename), $filename);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->backups->delete($filename);

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_deleted', 'Deleted backup '.$filename, ['file' => $filename]);

        return back()->with('success', 'Backup deleted.');
    }

    public function restore(string $filename): RedirectResponse
    {
        try {
            $this->backups->restore($filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => 'Restore failed: '.$e->getMessage()]);
        }

        ActionLogger::log(ActionLogger::CATEGORY_SETTINGS, 'backup_restored', 'Restored the database from '.$filename, ['file' => $filename]);

        return back()->with('success', 'Database restored from '.$filename.'.');
    }
}
