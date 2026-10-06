<?php

namespace App\Services;

use App\Support\SqlDumpGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Database backup + restore over spatie/laravel-backup. Backups are DB-only zip
 * archives on the local "backups" disk; restore extracts the SQL dump from a
 * chosen (or uploaded) archive and pipes it into mysql (taking a safety backup
 * first).
 *
 * Restore is destructive — it replaces the current database — so it is only
 * reachable from the super-admin BackupController.
 */
class BackupService
{
    /** Subdirectory (on the backups disk) spatie writes archives into. */
    private function directory(): string
    {
        return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
    }

    /** Subdirectory (inside the backup directory) holding archived backups. */
    private function archiveDirectory(): string
    {
        return $this->directory().'/archive';
    }

    private function disk()
    {
        return Storage::disk('backups');
    }

    /**
     * @return array<int,array{name:string, size:int, last_modified:int}>
     */
    public function list(): array
    {
        return $this->listIn($this->directory());
    }

    /**
     * Archived backups live in a subdirectory, so list() (which is
     * non-recursive) never mixes them into the active set.
     *
     * @return array<int,array{name:string, size:int, last_modified:int}>
     */
    public function listArchived(): array
    {
        return $this->listIn($this->archiveDirectory());
    }

    /**
     * @return array<int,array{name:string, size:int, last_modified:int}>
     */
    private function listIn(string $dir): array
    {
        if (! $this->disk()->exists($dir)) {
            return [];
        }

        return collect($this->disk()->files($dir))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => $this->disk()->size($path),
                'last_modified' => $this->disk()->lastModified($path),
            ])
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    /**
     * Run a DB-only backup now. Returns the name of the archive it produced, or
     * null if the new file could not be identified.
     */
    public function create(): ?string
    {
        $before = collect($this->list())->pluck('name')->all();

        Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        return collect($this->list())->pluck('name')->diff($before)->first();
    }

    /**
     * Absolute filesystem path of a stored backup, validated to the backups
     * directory (guards against traversal).
     */
    public function path(string $filename, bool $archived = false): string
    {
        $relative = ($archived ? $this->archiveDirectory() : $this->directory()).'/'.basename($filename);
        if (! $this->disk()->exists($relative)) {
            throw new RuntimeException('Backup not found.');
        }

        return $this->disk()->path($relative);
    }

    public function delete(string $filename, bool $archived = false): void
    {
        $relative = ($archived ? $this->archiveDirectory() : $this->directory()).'/'.basename($filename);
        if ($this->disk()->exists($relative)) {
            $this->disk()->delete($relative);
        }
    }

    /**
     * Move an active backup into the archive subdirectory.
     */
    public function archive(string $filename): void
    {
        $this->move($filename, false);
    }

    /**
     * Move an archived backup back into the active list.
     */
    public function unarchive(string $filename): void
    {
        $this->move($filename, true);
    }

    private function move(string $filename, bool $fromArchive): void
    {
        $from = ($fromArchive ? $this->archiveDirectory() : $this->directory()).'/'.basename($filename);
        $to = ($fromArchive ? $this->directory() : $this->archiveDirectory()).'/'.basename($filename);

        if (! $this->disk()->exists($from)) {
            throw new RuntimeException('Backup not found.');
        }

        $this->disk()->makeDirectory(dirname($to));
        $this->disk()->move($from, $to);
    }

    /**
     * Restore the database from a stored backup. Takes a safety backup, extracts
     * the SQL dump and pipes it into mysql. Throws on any failure so the caller
     * can surface it without leaving a half-applied state.
     *
     * Returns the name of the safety backup taken of the pre-restore state, so
     * the caller can tell the user about the archive that just appeared.
     */
    public function restore(string $filename): ?string
    {
        return $this->restoreFromArchive($this->path($filename));
    }

    /**
     * Restore the database from an uploaded backup archive (e.g. one previously
     * downloaded from this page). Uploaded dumps are untrusted, so they are
     * screened for mysql client commands before anything is written.
     */
    public function restoreFromUpload(UploadedFile $file): ?string
    {
        return $this->restoreFromArchive((string) $file->getRealPath(), untrusted: true);
    }

    private function restoreFromArchive(string $archivePath, bool $untrusted = false): ?string
    {
        // 1. Extract (and, for uploads, screen) the SQL dump first, so a bad
        //    archive fails before we have written anything (including a
        //    pointless safety backup).
        $sqlPath = $this->extractSqlDump($archivePath);
        $safety = null;

        try {
            if ($untrusted) {
                SqlDumpGuard::assertSafe($sqlPath);
            }

            // 2. Safety backup of the current state before we overwrite it.
            $safety = $this->create();

            // 3. Pipe the dump into the database.
            $this->importSql($sqlPath);
        } finally {
            @unlink($sqlPath);
        }

        return $safety;
    }

    /**
     * Extract the first *.sql entry from the backup zip to a temp file and
     * return its path.
     */
    private function extractSqlDump(string $archivePath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Could not open the backup archive.');
        }

        if ($password = config('backup.backup.password')) {
            $zip->setPassword((string) $password);
        }

        $sqlEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.sql')) {
                $sqlEntry = $name;
                break;
            }
        }

        if ($sqlEntry === null) {
            $zip->close();
            throw new RuntimeException('The backup archive contains no SQL dump.');
        }

        // Stream to disk rather than buffering: uploaded dumps can be large.
        $source = $zip->getStream($sqlEntry);
        $tmp = tempnam(sys_get_temp_dir(), 'restore_');
        $target = $tmp === false ? false : fopen($tmp, 'w');
        $copied = $source !== false && $target !== false && stream_copy_to_stream($source, $target) !== false;

        if (is_resource($source)) {
            fclose($source);
        }
        if (is_resource($target)) {
            fclose($target);
        }
        $zip->close();

        if (! $copied || filesize($tmp) === 0) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            throw new RuntimeException('Could not read the SQL dump from the archive.');
        }

        return $tmp;
    }

    /**
     * Pipe a SQL file into the configured mysql database using the mysql client.
     */
    private function importSql(string $sqlPath): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $handle = fopen($sqlPath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read the extracted SQL dump.');
        }

        // No shell: the dump is streamed to the client's stdin and the password
        // goes through MYSQL_PWD so it never lands in the process list.
        $process = new Process(
            [
                'mysql',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? '3306'),
                '--user='.($config['username'] ?? 'root'),
                '--local-infile=0',
                (string) ($config['database'] ?? ''),
            ],
            null,
            ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
            $handle,
        );
        $process->setTimeout(600);

        try {
            $process->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Database restore failed: '.trim($process->getErrorOutput()));
        }
    }
}
