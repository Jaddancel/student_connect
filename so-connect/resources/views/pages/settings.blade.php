@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Settings" />

    <div class="mx-auto max-w-3xl space-y-6">

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-xl border border-success-300 bg-success-50 px-4 py-3 dark:border-success-500/40 dark:bg-success-500/10">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-success-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" />
                </svg>
                <p class="text-sm text-success-700 dark:text-success-400">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- ============================= Account (all roles) ============================= --}}
        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Account</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your sign-in security and notifications.</p>

            {{-- Login notification toggle --}}
            <form method="POST" action="{{ route('settings.notifications') }}" class="mt-6"
                x-data="{ enabled: {{ $notifyOnLogin ? 'true' : 'false' }} }">
                @csrf
                <input type="hidden" name="notify_on_login" :value="enabled ? 1 : 0">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Email me about new sign-ins</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Get an email whenever your account is used to log in.</p>
                    </div>
                    <button type="button" @click="enabled = !enabled"
                        :class="enabled ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-700'"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                        <span :class="enabled ? 'translate-x-6' : 'translate-x-1'"
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                    </button>
                </div>
                <div class="mt-4">
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">
                        Save notification preference
                    </button>
                </div>
            </form>

            {{-- Password change (implemented in Phase 2) --}}
            <div class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
                @includeWhen(view()->exists('pages.settings.partials.password-form'), 'pages.settings.partials.password-form')
                @unless (view()->exists('pages.settings.partials.password-form'))
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</p>
                    <a href="{{ route('password.change') }}"
                        class="mt-2 inline-block text-sm font-medium text-brand-500 hover:text-brand-600">Change your password &rarr;</a>
                @endunless
            </div>
        </section>

        {{-- ========================= Notification window (type 2) ======================= --}}
        @if ($userType === 2)
            <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Notifications</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Control when organizations are warned about accreditation.</p>

                <form method="POST" action="{{ route('settings.notify-days') }}" class="mt-6 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Warn organizations this many days before the deadline
                        </label>
                        <input type="number" name="notify_days" min="1" max="90" value="{{ old('notify_days', $accreditationNotifyDays) }}"
                            class="h-11 w-32 rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <button type="submit"
                        class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">
                        Save
                    </button>
                </form>
            </section>

            {{-- ===================== After event reports (type 2) ===================== --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">After Event Reports</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    How long after an event ends before it appears on the officials' After Event Form page and they are notified (bell and email) to file its report.
                </p>

                <form method="POST" action="{{ route('settings.after-event-days') }}" class="mt-6 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Days after an event ends
                        </label>
                        <input type="number" name="after_event_days" min="0" max="180" value="{{ old('after_event_days', $afterEventElapsedDays) }}"
                            class="h-11 w-32 rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                        @error('after_event_days')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                        class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">
                        Save
                    </button>
                </form>
            </section>

            {{-- ======================= Administrator: accreditation (type 2) ============ --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Accreditation conditions</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Define what an organization must submit to remain accredited each cycle.
                </p>
                @if (view()->exists('pages.settings.partials.accreditation-conditions'))
                    @include('pages.settings.partials.accreditation-conditions')
                @else
                    <p class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                        The condition editor is enabled with the accreditation feature.
                    </p>
                @endif
            </section>
        @endif

        {{-- ============================ Backup / Restore (type 1) ======================= --}}
        @if ($userType === 1)
            <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Backup &amp; Restore</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Automatic database backups run on this interval.</p>

                <form method="POST" action="{{ route('settings.backup-interval') }}" class="mt-6 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Backup every</label>
                        <select name="interval_hours"
                            class="h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                            @foreach ([6 => '6 hours', 12 => '12 hours', 24 => 'Daily', 48 => 'Every 2 days', 168 => 'Weekly'] as $hours => $label)
                                <option value="{{ $hours }}" @selected($backupIntervalHours === $hours)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">
                        Save
                    </button>
                </form>

                <div class="mt-4">
                    @if (\Illuminate\Support\Facades\Route::has('superadmin.backups.index'))
                        <a href="{{ route('superadmin.backups.index') }}"
                            class="text-sm font-medium text-brand-500 hover:text-brand-600">Manage backups &amp; restore &rarr;</a>
                    @endif
                </div>
            </section>
        @endif

    </div>
@endsection
