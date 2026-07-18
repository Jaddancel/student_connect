<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->get('/settings')->assertRedirect();
});

it('shows only the account section to officers', function () {
    $user = recordsUser(3);

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Account')
        ->assertDontSee('Accreditation conditions')
        ->assertDontSee('Backup &amp; Restore', false);
});

it('lets any user toggle their login notification and audits it', function () {
    $user = recordsUser(3);
    expect((bool) $user->notify_on_login)->toBeTrue();

    $this->actingAs($user)
        ->post('/settings/notifications', ['notify_on_login' => 0])
        ->assertRedirect();

    expect((bool) $user->fresh()->notify_on_login)->toBeFalse();

    expect(DB::table('action_logs')
        ->where('category', 'settings')
        ->where('action', 'login_notification_updated')
        ->exists())->toBeTrue();
});

it('shows the notification + accreditation sections to admins', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Accreditation conditions');
});

it('lets admins set the accreditation notify-days window', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->post('/settings/notify-days', ['notify_days' => 14])
        ->assertRedirect();

    expect((int) AppSetting::get('accreditation.notify_days'))->toBe(14);

    expect(DB::table('action_logs')
        ->where('category', 'settings')
        ->where('action', 'accreditation_notify_days_updated')
        ->exists())->toBeTrue();
});

it('rejects invalid notify-days values', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->from('/settings')
        ->post('/settings/notify-days', ['notify_days' => 0])
        ->assertRedirect('/settings')
        ->assertSessionHasErrors('notify_days');
});

it('forbids non-admins from writing admin settings', function () {
    $officer = recordsUser(3);

    $this->actingAs($officer)
        ->post('/settings/notify-days', ['notify_days' => 14])
        ->assertForbidden();
});

it('shows the backup section only to super admins and saves the interval', function () {
    $super = recordsUser(1);

    $this->actingAs($super)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Backup &amp; Restore', false);

    $this->actingAs($super)
        ->post('/settings/backup-interval', ['interval_hours' => 12])
        ->assertRedirect();

    expect((int) AppSetting::get('backup.interval_hours'))->toBe(12);
});

it('forbids non-super-admins from writing backup settings', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->post('/settings/backup-interval', ['interval_hours' => 12])
        ->assertForbidden();
});
