<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give every test the baseline state a real deployment has: one confirmed
     * superadmin.
     *
     * Every web request passes through {@see \App\Http\Middleware\EnsureSystemInitialized},
     * which funnels a system with no confirmed superadmin into first-run setup.
     * The testing database is not seeded, so without this each test would look
     * like a fresh install and every request would be redirected to /setup.
     * FirstRunSetupTest strips this account back out to exercise setup itself.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSystemInitialized();
    }

    protected function ensureSystemInitialized(): void
    {
        $alreadyInitialized = User::where('user_type', User::TYPE_SUPERADMIN)
            ->whereNotNull('email_verified_at')
            ->exists();

        if ($alreadyInitialized) {
            return;
        }

        User::create([
            'user_email'        => 'baseline-superadmin@tests.local',
            'user_password'     => Hash::make('Sup3r!Secret'),
            'user_type'         => User::TYPE_SUPERADMIN,
            'email_verified_at' => now(),
        ]);
    }
}
