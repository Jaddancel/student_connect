<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Superadmin-only database backup & restore.
 *
 * Backups are produced by spatie/laravel-backup (`backup:run --only-db`) and stored
 * on the dedicated `backups` disk. Restoration is not provided by the package, so we
 * extract the SQL dump from a backup zip and import it via the `mysql` client.
 *
 * All routes are guarded by the `auth` + `superadmin` middleware (see routes/web.php).
 */
class DatabaseBackupController extends Controller
{
    private const DISK = 'backups';

    /** Backups are written under a sub-directory named after the configured backup name. */
    private function backupDir(): string
    {
        return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
    }

    public function index()
    {
        return view('pages.sidebar.superadmin-database-backup', [
            'title'   => 'Database Backup & Restore',
            'backups' => $this->listBackups(),
        ]);
    }

    public function run(): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
            $output   = Artisan::output();
        } catch (\Throwable $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['backup' => 'Backup failed: '.$e->getMessage()]);
        }

        if ($exitCode !== 0 || stripos($output, 'backup failed') !== false) {
            Log::error('Database backup reported failure', ['output' => $output]);

            return back()->withErrors(['backup' => 'Backup did not complete successfully. Check the application logs.']);
        }

        return back()->with('success', 'Database backup created successfully.');
    }

    public function download(string $file): StreamedResponse
    {
        $path = $this->resolveBackupPath($file);

        return Storage::disk(self::DISK)->download($path);
    }

    public function destroy(string $file): RedirectResponse
    {
        $path = $this->resolveBackupPath($file);

        Storage::disk(self::DISK)->delete($path);

        return back()->with('success', 'Backup “'.basename($path).'” deleted.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file'         => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ]);

        // Destructive action: require the user to type the exact confirmation phrase.
        if (trim($validated['confirmation']) !== 'RESTORE') {
            return back()->withErrors([
                'confirmation' => 'Type RESTORE (in capitals) to confirm the restore.',
            ]);
        }

        $path = $this->resolveBackupPath($validated['file']);
        $absoluteZip = Storage::disk(self::DISK)->path($path);

        try {
            $sql = $this->extractSqlFromZip($absoluteZip);
        } catch (\Throwable $e) {
            Log::error('Database restore: could not read backup', ['error' => $e->getMessage()]);

            return back()->withErrors(['restore' => 'Could not read the SQL dump from the backup: '.$e->getMessage()]);
        }

        try {
            $this->importSql($sql);
        } catch (\Throwable $e) {
            Log::error('Database restore failed during import', ['error' => $e->getMessage()]);

            return back()->withErrors(['restore' => 'Restore failed during import: '.$e->getMessage()]);
        }

        return back()->with('success', 'Database restored from “'.basename($path).'”.');
    }

    /**
     * @return array<int, array{name: string, size: string, created_at: string}>
     */
    private function listBackups(): array
    {
        $disk = Storage::disk(self::DISK);
        $dir  = $this->backupDir();

        $files = collect($disk->files($dir))
            ->filter(fn (string $p) => str_ends_with(strtolower($p), '.zip'))
            ->sortByDesc(fn (string $p) => $disk->lastModified($p))
            ->values();

        return $files->map(fn (string $p) => [
            'name'       => basename($p),
            'size'       => $this->humanSize($disk->size($p)),
            'created_at' => Carbon::createFromTimestamp($disk->lastModified($p))->format('M j, Y g:i A'),
        ])->all();
    }

    /**
     * Resolve a user-supplied backup file name to a safe path on the backups disk.
     * Only a bare file name within the backup directory is accepted (no traversal).
     */
    private function resolveBackupPath(string $file): string
    {
        $name = basename($file);

        if ($name === '' || ! str_ends_with(strtolower($name), '.zip')) {
            abort(404);
        }

        $path = $this->backupDir().'/'.$name;

        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return $path;
    }

    private function extractSqlFromZip(string $absoluteZip): string
    {
        $zip = new ZipArchive();

        if ($zip->open($absoluteZip) !== true) {
            throw new \RuntimeException('Unable to open backup archive.');
        }

        $sql = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (str_ends_with(strtolower($entry), '.sql')) {
                $sql = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if ($sql === null || $sql === false) {
            throw new \RuntimeException('No SQL dump found inside the backup archive.');
        }

        return $sql;
    }

    private function importSql(string $sql): void
    {
        $db = config('database.connections.'.config('database.default'));

        // Password passed via env (MYSQL_PWD) rather than CLI arg to keep it out of the
        // process list, and command uses an argument array (no shell interpolation).
        $process = new Process(
            [
                'mysql',
                '--host='.($db['host'] ?? '127.0.0.1'),
                '--port='.(string) ($db['port'] ?? '3306'),
                '--user='.($db['username'] ?? 'root'),
                $db['database'] ?? '',
            ],
            base_path(),
            ['MYSQL_PWD' => (string) ($db['password'] ?? '')],
            $sql,
            300.0,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput() ?: 'mysql import exited with an error.');
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
