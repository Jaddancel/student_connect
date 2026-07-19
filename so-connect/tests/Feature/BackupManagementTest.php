<?php

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertSee('2026-07-19-00-00-00.zip');
});

it('lists and deletes backup files', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put(backupDir().'/b1.zip', 'x');
    $service = app(BackupService::class);

    expect(collect($service->list())->pluck('name'))->toContain('b1.zip');

    $service->delete('b1.zip');
    expect(Storage::disk('backups')->exists(backupDir().'/b1.zip'))->toBeFalse();
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
