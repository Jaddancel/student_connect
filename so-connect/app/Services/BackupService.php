<?php

namespace App\Services;

use App\Services\ConfigBackup\ConfigExporter;
use App\Services\ConfigBackup\ConfigImporter;
use App\Support\SqlDumpGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup + restore for two backup types, stored as zip archives on the local
 * "backups" disk:
 *
 *  - Database backups (spatie/laravel-backup, DB-only): restore extracts the
 *    SQL dump and replaces the whole database.
 *  - Configuration backups (ConfigExporter, "config-" prefix): settings,
 *    forms, report templates, Step 2 templates and tally configuration.
 *    Restore merges them in (ConfigImporter) without deleting anything.
 *
 * Configuration archives live in a sibling "<name>-config" directory because
 * spatie's backup:clean scans "<name>/" recursively and would prune them.
 * Every restore takes a safety backup of the same type first. Only reachable
 * from the super-admin BackupController.
 */
class BackupService
{
    public const TYPE_DATABASE = 'database';

    public const TYPE_CONFIGURATION = 'configuration';

    public const TYPES = [self::TYPE_DATABASE, self::TYPE_CONFIGURATION];

    private const CONFIG_PREFIX = 'config-';

    public function __construct(private readonly ConfigExporter $exporter, private readonly ConfigImporter $importer) {}

    /** Subdirectory (on the backups disk) spatie writes archives into. */
    private function directory(): string
    {
        return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
    }

    private function directoryFor(string $type, bool $archived = false): string
    {
        $dir = $type === self::TYPE_CONFIGURATION ? $this->directory().'-config' : $this->directory();

        return $archived ? $dir.'/archive' : $dir;
    }

    public static function typeOf(string $filename): string
    {
        return str_starts_with(basename($filename), self::CONFIG_PREFIX) ? self::TYPE_CONFIGURATION : self::TYPE_DATABASE;
    }

    private function relative(string $filename, bool $archived): string
    {
        return $this->directoryFor(self::typeOf($filename), $archived).'/'.basename($filename);
    }

    private function disk()
    {
        return Storage::disk('backups');
    }

    /**
     * Active backups of both types, newest first.
     *
     * @return array<int,array{name:string, type:string, size:int, last_modified:int}>
     */
    public function list(): array
    {
        return $this->listBoth(false);
    }

    /**
     * Archived backups live in an "archive" subdirectory, so list() (which is
     * non-recursive) never mixes them into the active set.
     *
     * @return array<int,array{name:string, type:string, size:int, last_modified:int}>
     */
    public function listArchived(): array
    {
        return $this->listBoth(true);
    }

    /**
     * @return array<int,array{name:string, type:string, size:int, last_modified:int}>
     */
    private function listBoth(bool $archived): array
    {
        return collect(self::TYPES)
            ->flatMap(fn (string $type) => $this->listIn($this->directoryFor($type, $archived), $type))
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{name:string, type:string, size:int, last_modified:int}>
     */
    private function listIn(string $dir, string $type): array
    {
        if (! $this->disk()->exists($dir)) {
            return [];
        }

        return collect($this->disk()->files($dir))
            ->filter(fn (string $path) => str_ends_with($path, '.zip') && self::typeOf($path) === $type)
            ->map(fn (string $path) => [
                'name' => basename($path),
                'type' => $type,
                'size' => $this->disk()->size($path),
                'last_modified' => $this->disk()->lastModified($path),
            ])
            ->values()
            ->all();
    }

    /**
     * Run a backup of the given type now. Returns the name of the archive it
     * produced, or null if the new file could not be identified.
     */
    public function create(string $type = self::TYPE_DATABASE): ?string
    {
        if ($type === self::TYPE_CONFIGURATION) {
            return $this->createConfiguration();
        }

        $dir = $this->directoryFor(self::TYPE_DATABASE);
        $before = collect($this->listIn($dir, self::TYPE_DATABASE))->pluck('name')->all();

        Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        return collect($this->listIn($dir, self::TYPE_DATABASE))->pluck('name')->diff($before)->first();
    }

    private function createConfiguration(): string
    {
        $dir = $this->directoryFor(self::TYPE_CONFIGURATION);
        $this->disk()->makeDirectory($dir);

        $stamp = now()->format('Y-m-d-H-i-s');
        $name = self::CONFIG_PREFIX.$stamp.'.zip';
        for ($i = 2; $this->disk()->exists($dir.'/'.$name); $i++) {
            $name = self::CONFIG_PREFIX.$stamp.'-'.$i.'.zip';
        }

        $path = $this->disk()->path($dir.'/'.$name);
        try {
            $this->exporter->export($path);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $name;
    }

    /**
     * Absolute filesystem path of a stored backup, validated to the backups
     * directory (guards against traversal).
     */
    public function path(string $filename, bool $archived = false): string
    {
        $relative = $this->relative($filename, $archived);
        if (! $this->disk()->exists($relative)) {
            throw new RuntimeException('Backup not found.');
        }

        return $this->disk()->path($relative);
    }

    public function delete(string $filename, bool $archived = false): void
    {
        $relative = $this->relative($filename, $archived);
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
        $from = $this->relative($filename, $fromArchive);
        $to = $this->relative($filename, ! $fromArchive);

        if (! $this->disk()->exists($from)) {
            throw new RuntimeException('Backup not found.');
        }

        $this->disk()->makeDirectory(dirname($to));
        $this->disk()->move($from, $to);
    }

    /**
     * Restore from a stored backup, by its type. Throws on any failure so the
     * caller can surface it without leaving a half-applied state.
     *
     * Returns the type, the name of the safety backup taken first (so the
     * caller can explain the archive that just appeared) and, for
     * configuration restores, a summary of what was created/updated.
     *
     * @return array{type:string, safety:?string, summary:?array}
     */
    public function restore(string $filename): array
    {
        $path = $this->path($filename);

        return self::typeOf($filename) === self::TYPE_CONFIGURATION
            ? $this->restoreConfiguration($path)
            : $this->restoreFromArchive($path);
    }

    /**
     * Restore from an uploaded backup archive (e.g. one previously downloaded
     * from this page). The type is detected from the archive itself. Uploaded
     * SQL dumps are untrusted, so they are screened for mysql client commands
     * before anything is written.
     *
     * @return array{type:string, safety:?string, summary:?array}
     */
    public function restoreFromUpload(UploadedFile $file): array
    {
        $path = (string) $file->getRealPath();

        return ConfigImporter::isConfigArchive($path)
            ? $this->restoreConfiguration($path)
            : $this->restoreFromArchive($path, untrusted: true);
    }

    /**
     * @return array{type:string, safety:string, summary:array}
     */
    private function restoreConfiguration(string $archivePath): array
    {
        // Reject a bad archive before writing anything (incl. the safety backup).
        $this->importer->validate($archivePath);
        $safety = $this->createConfiguration();

        return [
            'type' => self::TYPE_CONFIGURATION,
            'safety' => $safety,
            'summary' => $this->importer->import($archivePath),
        ];
    }

    /**
     * @return array{type:string, safety:string, summary:null}
     */
    private function restoreFromArchive(string $archivePath, bool $untrusted = false): array
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

            // 2. Safety backup of the current state before we overwrite it. The
            //    wipe below is irreversible without it, so refuse to continue
            //    if no new archive appeared.
            $safety = $this->create();
            if ($safety === null) {
                throw new RuntimeException('Could not take a safety backup of the current database; nothing was restored.');
            }

            // 3. Drop every existing table, so tables created after the backup
            //    was taken don't survive alongside the restored ones (the dump
            //    only drops/recreates the tables it contains).
            $this->wipeDatabase();

            // 4. Pipe the dump into the database.
            $this->importSql($sqlPath);
        } finally {
            @unlink($sqlPath);
        }

        return ['type' => self::TYPE_DATABASE, 'safety' => $safety, 'summary' => null];
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

    private function wipeDatabase(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        $schema->dropAllViews();
        $schema->dropAllTables();
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
