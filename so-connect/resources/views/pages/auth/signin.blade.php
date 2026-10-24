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

                </div>

                <div class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Do not have an account?</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Registration is separate from sign in. Choose the option that matches your goal.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <a href="{{ route('signup') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-left transition hover:border-[#2d6a4f] hover:bg-emerald-50 dark:border-gray-700 dark:hover:bg-emerald-500/10">
                            <strong class="block text-xs text-gray-800 dark:text-white">Student officer account</strong>
                            <span class="mt-1 block text-[11px] leading-4 text-gray-500 dark:text-gray-400">Join an existing organization</span>
                        </a>
                        <a href="{{ route('forms.render', 'new-organization-registration') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-left transition hover:border-[#2d6a4f] hover:bg-emerald-50 dark:border-gray-700 dark:hover:bg-emerald-500/10">
                            <strong class="block text-xs text-gray-800 dark:text-white">New organization application</strong>
                            <span class="mt-1 block text-[11px] leading-4 text-gray-500 dark:text-gray-400">Apply as a founding group</span>
                        </a>
                    </div>
                </div>

                <p class="mt-5 text-center text-xs leading-5 text-gray-400">Authorized student leaders and administrators only. Account activity may be recorded for security.</p>
            </div>
        </section>
    </main>
@endsection
