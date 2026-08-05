<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Database backup + restore over spatie/laravel-backup. Backups are DB-only zip
 * archives on the local "backups" disk; restore extracts the SQL dump from a
 * chosen archive and pipes it into mysql (taking a safety backup first).
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

    private function disk()
    {
        return Storage::disk('backups');
    }

    /**
     * @return array<int,array{name:string, size:int, last_modified:int}>
     */
    public function list(): array
    {
        $dir = $this->directory();
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
    public function path(string $filename): string
    {
        $relative = $this->directory().'/'.basename($filename);
        if (! $this->disk()->exists($relative)) {
            throw new RuntimeException('Backup not found.');
        }

        return $this->disk()->path($relative);
    }

    public function delete(string $filename): void
    {
        $relative = $this->directory().'/'.basename($filename);
        if ($this->disk()->exists($relative)) {
            $this->disk()->delete($relative);
        }
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
        $archivePath = $this->path($filename);

        // 1. Extract the SQL dump first, so a bad archive fails before we have
        //    written anything (including a pointless safety backup).
        $sqlPath = $this->extractSqlDump($archivePath);
        $safety = null;

        try {
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
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Could not open the backup archive.');
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

        $contents = $zip->getFromName($sqlEntry);
        $zip->close();

        if ($contents === false) {
            throw new RuntimeException('Could not read the SQL dump from the archive.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'restore_').'.sql';
        file_put_contents($tmp, $contents);

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
