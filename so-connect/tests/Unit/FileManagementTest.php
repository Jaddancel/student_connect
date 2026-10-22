<?php

use App\Services\BackupService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

beforeEach(function () {
    $this->backupRoot = sys_get_temp_dir().'/backup-unit-'.bin2hex(random_bytes(8));
    config([
        'backup.backup.name' => 'unit-backups',
        'filesystems.disks.backups' => [
            'driver' => 'local',
            'root' => $this->backupRoot,
            'throw' => false,
        ],
    ]);
    Storage::forgetDisk('backups');
});

afterEach(function () {
    File::deleteDirectory($this->backupRoot);
    Storage::forgetDisk('backups');
});

it('classifies configuration backups by their filename', function () {
    expect(BackupService::typeOf('2026-10-22.zip'))->toBe(BackupService::TYPE_DATABASE)
        ->and(BackupService::typeOf('config-2026-10-22.zip'))->toBe(BackupService::TYPE_CONFIGURATION)
        ->and(BackupService::typeOf('../config-2026-10-22.zip'))->toBe(BackupService::TYPE_CONFIGURATION);
});

it('resolves backup paths by basename and rejects files outside the backup store', function () {
    Storage::disk('backups')->put('unit-backups-config/config-snapshot.zip', 'archive');
    $service = app(BackupService::class);

    expect($service->path('../../config-snapshot.zip'))
        ->toBe(Storage::disk('backups')->path('unit-backups-config/config-snapshot.zip'));

    expect(fn () => $service->path('../../outside.zip'))
        ->toThrow(RuntimeException::class, 'Backup not found.');
});
