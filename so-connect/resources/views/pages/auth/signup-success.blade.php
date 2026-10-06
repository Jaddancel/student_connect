@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex min-h-screen w-full items-center justify-center bg-white px-4 py-10 dark:bg-gray-900">
        <div class="w-full max-w-lg">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="h-1 w-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale"></div>

                <div class="p-6 sm:p-8">
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-success-50 dark:bg-success-500/15">
                        <svg class="h-6 w-6 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <h1 class="text-title-sm mb-2 font-semibold text-gray-800 dark:text-white/90">
                        Sign-up submitted
                    </h1>

                    @if ($confirmed)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Your request has been sent to the administrators for review. You can follow its
                            progress from your dashboard at any time.
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            We've sent an account confirmation email
                            @if ($email)
                                to <span class="font-medium text-gray-700 dark:text-gray-200">{{ $email }}</span>
                            @endif
                            . Open it and confirm your address to activate your access.
                        </p>
                    @endif

                    <ol class="mt-6 space-y-4">
                        @foreach ([
                            ['Confirm your email', 'Click the link in the email we just sent you. It expires in 7 days.', ! $confirmed],
                            ['Sign in as a guest', 'Guest access lets you sign in and follow your request while it is reviewed.', false],
                            ['Wait for approval', 'An administrator reviews your details. Once approved, your guest account becomes a full officer account.', false],
                        ] as $index => [$stepTitle, $stepBody, $isNext])
                            <li class="flex gap-3">
                                <span @class([
                                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    'bg-palette-lime text-gray-900' => $isNext,
                                    'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' => ! $isNext,
                                ])>{{ $index + 1 }}</span>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $stepTitle }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stepBody }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <a href="{{ $confirmed ? route('guest.dashboard') : route('login') }}"
                            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            {{ $confirmed ? 'Go to my dashboard' : 'Go to sign in' }}
                        </a>
                        <a href="{{ url('/') }}" class="text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400">
                            Back to home
                        </a>
                    </div>

                    @unless ($confirmed)
                        <p class="mt-5 text-xs text-gray-400 dark:text-gray-500">
                            No email after a few minutes? Check your spam folder — the message comes from
                            StudentConnect and contains your confirmation link.
                        </p>
                    @endunless
                </div>
            </div>
        </div>
    </div>
@endsection
