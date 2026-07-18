<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Rules\StrongPassword;
use App\Services\ActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Central settings page. Sections render/persist per role:
 *  - Account (all): login-notification toggle + password change (Phase 2).
 *  - Notification (type 2): accreditation warning window (notify days).
 *  - Administrator (type 2): accreditation condition editor (Phase 5).
 *  - Backup/Restore (type 1): interval + link to /superadmin/backups (Phase 3).
 * Every write is audited via ActionLogger category "settings".
 */
class SettingsController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $type = (int) $user->user_type;

        return view('pages.settings', [
            'title' => 'Settings',
            'userType' => $type,
            'notifyOnLogin' => (bool) $user->notify_on_login,
            'accreditationNotifyDays' => (int) AppSetting::get('accreditation.notify_days', 7),
            'accreditationPurgeGraceDays' => (int) AppSetting::get('accreditation.purge_grace_days', 30),
            'backupIntervalHours' => (int) AppSetting::get('backup.interval_hours', 24),
        ]);
    }

    /**
     * Account section: toggle the "new login" notification email opt-in.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_on_login' => ['nullable', 'boolean'],
        ]);

        $enabled = (bool) ($validated['notify_on_login'] ?? false);

        $user = $request->user();
        $user->forceFill(['notify_on_login' => $enabled])->save();

        ActionLogger::log(
            ActionLogger::CATEGORY_SETTINGS,
            'login_notification_updated',
            'Login notification '.($enabled ? 'enabled' : 'disabled'),
            ['notify_on_login' => $enabled],
        );

        return back()->with('status', 'Notification preferences saved.');
    }

    /**
     * Account section: self-service password change. Verifies the current
     * password against the non-standard user_password column, enforces the
     * shared strong-password policy, and rejects reusing the current password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', new StrongPassword, 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->user_password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        if (Hash::check($request->input('password'), $user->user_password)) {
            throw ValidationException::withMessages([
                'password' => 'Your new password must be different from your current password.',
            ]);
        }

        $user->forceFill([
            'user_password'         => Hash::make($request->input('password')),
            'force_password_change' => false,
        ])->save();

        ActionLogger::log(
            ActionLogger::CATEGORY_AUTH,
            'password_changed',
            'Password changed from settings',
            [],
            null,
            (int) $user->getKey(),
        );

        return back()->with('status', 'Password updated successfully.');
    }

    /**
     * Notification section (type 2): how many days before the accreditation
     * deadline the danger card / warning email starts showing.
     */
    public function updateNotifyDays(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'notify_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        AppSetting::put('accreditation.notify_days', (int) $validated['notify_days']);

        ActionLogger::log(
            ActionLogger::CATEGORY_SETTINGS,
            'accreditation_notify_days_updated',
            'Accreditation warning window set to '.$validated['notify_days'].' days',
            ['notify_days' => (int) $validated['notify_days']],
        );

        return back()->with('status', 'Notification window saved.');
    }

    /**
     * Backup section (type 1): automatic backup interval in hours.
     */
    public function updateBackupInterval(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'interval_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        AppSetting::put('backup.interval_hours', (int) $validated['interval_hours']);

        ActionLogger::log(
            ActionLogger::CATEGORY_SETTINGS,
            'backup_interval_updated',
            'Automatic backup interval set to '.$validated['interval_hours'].' hours',
            ['interval_hours' => (int) $validated['interval_hours']],
        );

        return back()->with('status', 'Backup interval saved.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless((int) $request->user()->user_type === 2, 403);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless((int) $request->user()->user_type === 1, 403);
    }
}
