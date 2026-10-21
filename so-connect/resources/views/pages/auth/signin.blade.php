@extends('layouts.fullscreen-layout')

@section('content')
    <main class="min-h-screen bg-slate-50 dark:bg-gray-950 lg:grid lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden min-h-screen overflow-hidden lg:flex lg:flex-col lg:justify-between">
            <img src="{{ asset('images/landing/BG1.jpg') }}" alt="Tarlac Agricultural University campus community" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[#10271d]/95 via-[#1a3c2e]/90 to-[#2d6a4f]/80"></div>
            <div class="absolute -left-24 top-1/3 h-80 w-80 rounded-full border border-amber-300/15"></div>
            <div class="absolute -left-10 top-1/3 h-56 w-56 rounded-full border border-amber-300/20"></div>

            <a href="{{ route('home') }}" class="relative z-10 m-10 inline-flex w-fit items-center gap-3 text-white">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-300/40 bg-white/10 text-sm font-bold text-amber-300 backdrop-blur">SO</span>
                <span><strong class="block text-base tracking-wide">SO Connect</strong><small class="text-white/60">Tarlac Agricultural University</small></span>
            </a>

            <div class="relative z-10 max-w-xl px-12 pb-12 xl:px-16 xl:pb-16">
                <p class="mb-5 text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">One connected campus</p>
                <h1 class="text-4xl font-semibold leading-tight text-white xl:text-5xl">Lead, organize, and grow your student community.</h1>
                <p class="mt-6 max-w-lg text-base leading-7 text-white/70">Manage organization records, activity requests, documents, and campus participation through one secure platform.</p>
                <div class="mt-8 grid grid-cols-3 gap-3 text-sm text-white/70">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><strong class="block text-white">Discover</strong>Organizations</div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><strong class="block text-white">Plan</strong>Activities</div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur"><strong class="block text-white">Connect</strong>Students</div>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 text-gray-900 dark:text-white">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1a3c2e] text-xs font-bold text-amber-300">SO</span>
                        <strong>SO Connect</strong>
                    </a>
                </div>

                <a href="{{ route('home') }}" class="mb-8 hidden w-fit items-center gap-2 text-sm font-medium text-gray-500 transition hover:text-[#2d6a4f] lg:inline-flex dark:text-gray-400">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12.5 15 7.5 10l5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Back to public site
                </a>

                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none">
                    <div class="mb-7">
                        <span class="mb-4 block h-1 w-10 rounded-full bg-amber-400"></span>
                        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Welcome back</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Sign in with your registered SO Connect account.</p>
                    </div>

                    @if ($errors->has('user_email'))
                        <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0v-4ZM10 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                            <span>{{ $errors->first('user_email') }}</span>
                        </div>
                    @endif

                    <form action="{{ url('/login') }}" method="POST" class="space-y-5">
                        @csrf
                        <div>
                            <label for="login-email" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
                            <input id="login-email" type="email" name="user_email" value="{{ old('user_email') }}" required autofocus autocomplete="email" placeholder="you@example.com"
                                class="h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#2d6a4f] focus:ring-4 focus:ring-emerald-600/10 dark:border-gray-700 dark:text-white">
                        </div>

                        <div x-data="{ showPassword: false, capsLock: false }">
                            <label for="login-password" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                            <div class="relative">
                                <input id="login-password" :type="showPassword ? 'text' : 'password'" name="user_password" required autocomplete="current-password" placeholder="Enter your password"
                                    @keydown="capsLock = $event.getModifierState('CapsLock')" @keyup="capsLock = $event.getModifierState('CapsLock')"
                                    class="h-12 w-full rounded-xl border border-gray-300 bg-transparent px-4 pr-12 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#2d6a4f] focus:ring-4 focus:ring-emerald-600/10 dark:border-gray-700 dark:text-white">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                    <svg x-show="!showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    <svg x-show="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 3 18 18M10.6 6.15A8.9 8.9 0 0 1 12 6c6 0 9.5 6 9.5 6a15.8 15.8 0 0 1-2.1 2.75M6.2 6.2C3.85 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.85-.38 4.02-.96M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <p x-show="capsLock" class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400">Caps Lock is on.</p>
                        </div>

                        <button type="submit" class="flex h-12 w-full items-center justify-center rounded-xl bg-[#1a3c2e] px-4 text-sm font-semibold text-white transition hover:bg-[#2d6a4f] focus:outline-none focus:ring-4 focus:ring-emerald-700/20">Sign in securely</button>
                    </form>

                    <div class="my-6 flex items-center gap-3"><span class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></span><span class="text-xs text-gray-400">New to SO Connect?</span><span class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></span></div>

                    <div class="space-y-3">
                        <button type="button" id="signup-google-btn" data-configured="{{ \App\Http\Controllers\Auth\GoogleLinkController::isConfigured() ? '1' : '0' }}" data-redirect-url="{{ route('admin.accounts.google.redirect') }}" data-signup-url="{{ route('signup') }}"
                            class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.23a4.47 4.47 0 0 1-1.94 2.93v2.79h3.14c1.84-1.69 2.92-4.19 2.92-7.75Z"/><path fill="#34A853" d="M12 21.75c2.63 0 4.84-.87 6.43-2.36l-3.14-2.43c-.87.58-1.98.92-3.29.92-2.54 0-4.69-1.72-5.46-4.03H3.3v2.88A9.72 9.72 0 0 0 12 21.75Z"/><path fill="#FBBC05" d="M6.54 13.85A5.84 5.84 0 0 1 6.23 12c0-.64.11-1.27.31-1.85V7.27H3.3A9.72 9.72 0 0 0 2.25 12c0 1.71.41 3.33 1.05 4.73l3.24-2.88Z"/><path fill="#EA4335" d="M12 6.12c1.43 0 2.71.49 3.72 1.45l2.78-2.78A9.35 9.35 0 0 0 12 2.25a9.72 9.72 0 0 0-8.7 5.02l3.24 2.88C7.31 7.84 9.46 6.12 12 6.12Z"/></svg>
                            Register with Google
                        </button>
                        <p id="signup-google-status" class="hidden text-center text-xs text-gray-500 dark:text-gray-400"></p>
                        <a href="{{ route('signup') }}" class="flex h-11 w-full items-center justify-center rounded-xl border border-[#2d6a4f] text-sm font-semibold text-[#2d6a4f] transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">Register as a student officer</a>
                    </div>
                </div>

                <p class="mt-6 text-center text-xs leading-5 text-gray-400">Authorized student leaders and administrators only. Account activity may be recorded for security.</p>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script @nonce>
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.getElementById('signup-google-btn');
            var status = document.getElementById('signup-google-status');
            if (!button || !status) return;

            button.addEventListener('click', function () {
                status.classList.remove('hidden', 'text-error-600');
                if (button.dataset.configured !== '1') {
                    status.classList.add('text-error-600');
                    status.textContent = 'Google registration is not configured yet.';
                    return;
                }
                status.textContent = 'Opening Google registration…';
                var width = 500, height = 600;
                window.open(button.dataset.redirectUrl, 'google-signup', 'width=' + width + ',height=' + height + ',left=' + (window.screenX + (window.outerWidth - width) / 2) + ',top=' + (window.screenY + (window.outerHeight - height) / 2));
            });

            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                var data = event.data || {};
                if (data.source !== 'google-link') return;
                if (data.error) {
                    status.classList.remove('hidden');
                    status.classList.add('text-error-600');
                    status.textContent = data.error;
                    return;
                }
                var params = new URLSearchParams({
                    google_id: data.google_id || '', first_name: data.first_name || '', last_name: data.last_name || '',
                    email: data.email || '', sex: data.sex || '', birthday: data.birthday || '', present_address: data.present_address || ''
                });
                window.location.href = button.dataset.signupUrl + '?' + params.toString();
            });
        });
    </script>
@endpush
