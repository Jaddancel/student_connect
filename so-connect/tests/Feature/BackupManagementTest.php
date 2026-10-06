<?php

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function backupDir(): string
{
    return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
}

it('forbids non-super-admins from the backups page', function () {
    $this->actingAs(recordsUser(2))
        ->get(route('superadmin.backups.index'))
        ->assertForbidden();
});

it('shows stored backups to a super admin', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/2026-07-19-00-00-00.zip', 'dummy');

    $this->actingAs(recordsUser(1))
        ->get(route('superadmin.backups.index'))
        ->assertOk()
        ->assertSee('2026-07-19-00-00-00.zip')
        ->assertSee(route('superadmin.backups.restore-upload'))
        ->assertSee('name="backup_file"', false);
});

it('shows archived backups on their own page only to super admins', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/archive/2026-07-19-00-00-00.zip', 'dummy');

    $this->actingAs(recordsUser(1))
        ->get(route('superadmin.backups.archived'))
        ->assertOk()
        ->assertSee('2026-07-19-00-00-00.zip');

    $this->actingAs(recordsUser(2))
        ->get(route('superadmin.backups.archived'))
        ->assertForbidden();

    // Archived backups do not show up on the active list page.
    $this->actingAs(recordsUser(1))
        ->get(route('superadmin.backups.index'))
        ->assertOk()
        ->assertDontSee('2026-07-19-00-00-00.zip');
});

it('lists and deletes backup files', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/b1.zip', 'x');
    $service = app(BackupService::class);

    expect(collect($service->list())->pluck('name'))->toContain('b1.zip');

    $service->delete('b1.zip');
    expect(Storage::disk('backups')->exists(backupDir().'/b1.zip'))->toBeFalse();
});

it('archives and unarchives backups, keeping them out of the active list', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/b1.zip', 'x');
    $service = app(BackupService::class);

    $service->archive('b1.zip');

    expect(collect($service->list())->pluck('name'))->not->toContain('b1.zip');
    expect(collect($service->listArchived())->pluck('name'))->toContain('b1.zip');

    $service->unarchive('b1.zip');

    expect(collect($service->list())->pluck('name'))->toContain('b1.zip');
    expect(collect($service->listArchived())->pluck('name'))->not->toContain('b1.zip');
});

it('deletes archived backups', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/archive/b1.zip', 'x');
    $service = app(BackupService::class);

    $service->delete('b1.zip', archived: true);

    expect(Storage::disk('backups')->exists(backupDir().'/archive/b1.zip'))->toBeFalse();
});

it('archives a backup via the archive action and audits it', function () {
    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('archive')->once()->with('b1.zip');
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.archive', 'b1.zip'))
        ->assertRedirect();

    expect(DB::table('action_logs')->where('action', 'backup_archived')->exists())->toBeTrue();
});

it('unarchives a backup via the unarchive action and audits it', function () {
    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('unarchive')->once()->with('b1.zip');
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.unarchive', 'b1.zip'))
        ->assertRedirect();

    expect(DB::table('action_logs')->where('action', 'backup_unarchived')->exists())->toBeTrue();
});

it('runs a backup via the store action and audits it', function () {
    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('create')->once();
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.store'))
        ->assertRedirect();

    expect(DB::table('action_logs')->where('action', 'backup_created')->exists())->toBeTrue();
});

it('restores from a backup via the restore action and audits it', function () {
    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('restore')->once()->with('b1.zip');
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.restore', 'b1.zip'))
        ->assertRedirect();

    expect(DB::table('action_logs')->where('action', 'backup_restored')->exists())->toBeTrue();
});

it('forbids non-super-admins from restoring', function () {
    $this->actingAs(recordsUser(2))
        ->post(route('superadmin.backups.restore', 'b1.zip'))
        ->assertForbidden();
});

function backupZipUpload(string $sql, string $name = 'downloaded.zip'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload_');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('db-dumps/mysql-laravel.sql', $sql);
    $zip->close();

    return new UploadedFile($path, $name, 'application/zip', null, true);
}

it('restores from an uploaded backup file and audits it', function () {
    $upload = backupZipUpload("SELECT 1;\n");

    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('restoreFromUpload')->once()->andReturn('safety.zip');
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.restore-upload'), ['backup_file' => $upload])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'downloaded.zip') && str_contains($msg, 'safety.zip'));

    $log = DB::table('action_logs')->where('action', 'backup_restored')->first();
    expect($log)->not->toBeNull();
});

it('rejects uploads that are not zip archives', function () {
    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldNotReceive('restoreFromUpload');
    });

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.restore-upload'), [
            'backup_file' => UploadedFile::fake()->create('dump.sql', 10, 'application/sql'),
        ])
        ->assertSessionHasErrors('backup_file');
});

it('forbids non-super-admins from restoring an uploaded file', function () {
    $this->actingAs(recordsUser(2))
        ->post(route('superadmin.backups.restore-upload'), ['backup_file' => backupZipUpload("SELECT 1;\n")])
        ->assertForbidden();
});

it('refuses uploaded dumps containing mysql client commands before touching the database', function () {
    $service = Mockery::mock(BackupService::class)->makePartial();
    $service->shouldNotReceive('create');

    expect(fn () => $service->restoreFromUpload(backupZipUpload("SELECT 1;\n\\! touch /tmp/pwned\n")))
        ->toThrow(RuntimeException::class, 'mysql client command');
});

it('refuses uploaded archives without an SQL dump', function () {
    $path = tempnam(sys_get_temp_dir(), 'upload_');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'hi');
    $zip->close();

    $service = Mockery::mock(BackupService::class)->makePartial();
    $service->shouldNotReceive('create');

    expect(fn () => $service->restoreFromUpload(new UploadedFile($path, 'x.zip', 'application/zip', null, true)))
        ->toThrow(RuntimeException::class, 'no SQL dump');
});

it('aborts a restore without wiping the database when no safety backup could be taken', function () {
    $service = Mockery::mock(BackupService::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturnNull();

    expect(fn () => $service->restoreFromUpload(backupZipUpload("SELECT 1;\n")))
        ->toThrow(RuntimeException::class, 'safety backup');

    expect(Illuminate\Support\Facades\Schema::hasTable('users'))->toBeTrue();
});

it('bulk archives selected backups and audits each one', function () {
    Storage::fake('backups');
    foreach (['b1.zip', 'b2.zip', 'b3.zip'] as $name) {
        Storage::disk('backups')->put(backupDir().'/'.$name, 'x');
    }

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.bulk'), ['action' => 'archive', 'filenames' => ['b1.zip', 'b2.zip']])
        ->assertRedirect()
        ->assertSessionHas('success', 'Archived 2 backups.');

    $service = app(BackupService::class);
    expect(collect($service->list())->pluck('name')->all())->toBe(['b3.zip']);
    expect(collect($service->listArchived())->pluck('name')->sort()->values()->all())->toBe(['b1.zip', 'b2.zip']);
    expect(DB::table('action_logs')->where('action', 'backup_archived')->count())->toBe(2);
});

it('bulk deletes selected backups and reports ones that could not be processed', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/b1.zip', 'x');
    Storage::disk('backups')->put(backupDir().'/b2.zip', 'x');

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.bulk'), ['action' => 'delete', 'filenames' => ['b1.zip', '../b2.zip']])
        ->assertRedirect()
        ->assertSessionHas('success', 'Deleted 2 backups.');

    expect(Storage::disk('backups')->files(backupDir()))->toBe([]);
    expect(DB::table('action_logs')->where('action', 'backup_deleted')->count())->toBe(2);

    Storage::disk('backups')->put(backupDir().'/b3.zip', 'x');

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.bulk'), ['action' => 'archive', 'filenames' => ['b3.zip', 'missing.zip']])
        ->assertSessionHas('success', 'Archived 1 backup.')
        ->assertSessionHasErrors('backup');
});

it('validates bulk backup requests', function () {
    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.bulk'), ['action' => 'restore', 'filenames' => []])
        ->assertSessionHasErrors(['action', 'filenames']);
});

it('forbids non-super-admins from bulk backup operations', function () {
    $this->actingAs(recordsUser(2))
        ->post(route('superadmin.backups.bulk'), ['action' => 'delete', 'filenames' => ['b1.zip']])
        ->assertForbidden();
});
