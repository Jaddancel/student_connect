@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 min-h-screen bg-white dark:bg-gray-900">
    <div class="flex min-h-screen w-full flex-col lg:flex-row">

        {{-- LEFT: Status --}}
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-12">

                <div class="mb-8">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <svg class="h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        Step 2 of 2 — Confirm your email
                    </div>

                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                        Check your inbox
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        The superadmin account has been created but is <strong class="font-semibold text-gray-700 dark:text-gray-300">not active yet</strong>.
                        Open the confirmation link we sent to activate it and sign in.
                    </p>
                </div>

                {{-- The address it went to --}}
                <div class="mb-5 flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-800/50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 text-brand-500">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Sent to</p>
                        <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $email }}</p>
                    </div>
                </div>

                {{-- Delivery status --}}
                @if (session('status'))
                    <div class="mb-4 flex items-start gap-3 rounded-lg border border-success-300 bg-success-50 px-4 py-3 dark:border-success-500/40 dark:bg-success-500/10">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-success-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" />
                        </svg>
                        <p class="text-sm text-success-700 dark:text-success-400">{{ session('status') }}</p>
                    </div>
                @endif

                @if (session('mail_error'))
                    <div class="mb-4 flex items-start gap-3 rounded-lg border border-error-300 bg-error-50 px-4 py-3 dark:border-error-500/40 dark:bg-error-500/10">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-error-500 dark:text-error-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-11.25a.75.75 0 011.5 0v4a.75.75 0 01-1.5 0v-4zm.75 7a1 1 0 100-2 1 1 0 000 2z" />
                        </svg>
                        <p class="text-sm text-error-700 dark:text-error-400">{{ session('mail_error') }}</p>
                    </div>
                @endif

                @if ($linkExpired)
                    <div class="mb-4 flex items-start gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-500/40 dark:bg-warning-500/10">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-warning-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-11.25a.75.75 0 011.5 0v4a.75.75 0 01-1.5 0v-4zm.75 7a1 1 0 100-2 1 1 0 000 2z" />
                        </svg>
                        <p class="text-sm text-warning-700 dark:text-warning-400">
                            The confirmation link has expired. Send a new one to continue.
                        </p>
                    </div>
                @elseif ($expiresAt)
                    <p class="mb-4 text-xs text-gray-400 dark:text-gray-500">
                        The link expires on {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('M j, Y \a\t g:i A') }}.
                    </p>
                @endif

                {{-- Actions --}}
                <form action="{{ route('setup.resend') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="shadow-theme-xs bg-brand-500 hover:bg-brand-600 flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white transition-colors duration-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Resend confirmation email
                    </button>
                </form>

                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Not arriving?</p>
                    <ul class="space-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                        <li>Check the spam or junk folder.</li>
                        <li>Confirm the mail service is configured and reachable.</li>
                        <li>
                            Typed the wrong address?
                            <form action="{{ route('setup.restart') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="cursor-pointer font-semibold text-brand-500 underline underline-offset-2 hover:text-brand-600">
                                    Start setup over
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>

                <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Already confirmed? <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600 font-medium">Sign in</a>
                </p>

            </div>
        </div>

        {{-- RIGHT: Visual Panel --}}
        <div class="bg-brand-950 relative hidden min-h-screen items-center lg:flex lg:w-1/2 dark:bg-white/5">
            <x-common.common-grid-shape />
            <div class="relative z-10 flex w-full flex-col items-center justify-center px-12 text-center">

                <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                    <svg class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </div>

                <h2 class="mb-3 text-2xl font-bold text-white">
                    One last step
                </h2>
                <p class="mb-8 max-w-xs text-sm leading-relaxed text-gray-400">
                    Confirming the address proves the superadmin mailbox is real and reachable — it is where every password reset and security notice will be sent.
                </p>

                <div class="w-full max-w-xs space-y-3 text-left">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">1</span>
                        <p class="text-sm text-gray-300">Open the email we just sent.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">2</span>
                        <p class="text-sm text-gray-300">Click <em>Confirm and activate</em>.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">3</span>
                        <p class="text-sm text-gray-300">You are signed in and the system is ready.</p>
                    </div>
                </div>
            </div>

            <div class="absolute right-6 bottom-6">
                <button
                    class="bg-brand-500 hover:bg-brand-600 inline-flex size-12 items-center justify-center rounded-full text-white transition-colors"
                    @click.prevent="$store.theme.toggle()">
                    <svg class="hidden fill-current dark:block" width="18" height="18" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001ZM15.9813 5.08035C16.2742 4.78746 16.2742 4.31258 15.9813 4.01969C15.6884 3.7268 15.2135 3.7268 14.9207 4.01969L14.0368 4.90357C13.7439 5.19647 13.7439 5.67134 14.0368 5.96423C14.3297 6.25713 14.8045 6.25713 15.0974 5.96423L15.9813 5.08035ZM18.4577 10.0001C18.4577 10.4143 18.1219 10.7501 17.7077 10.7501H16.4577C16.0435 10.7501 15.7077 10.4143 15.7077 10.0001C15.7077 9.58592 16.0435 9.25013 16.4577 9.25013H17.7077C18.1219 9.25013 18.4577 9.58592 18.4577 10.0001Z"/>
                    </svg>
                    <svg class="fill-current dark:hidden" width="18" height="18" viewBox="0 0 20 20">
                        <path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207Z"/>
                    </svg>
                </button>
            </div>
        </div>

    </div>
</div>
@endsection
