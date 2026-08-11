@extends('layouts.app')

@php
    $tone = match ($status) {
        'rejected' => ['label' => 'Not approved', 'chip' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500'],
        'approved' => ['label' => 'Approved', 'chip' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'],
        'pending' => ['label' => 'Awaiting review', 'chip' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400'],
        default => ['label' => 'No request on file', 'chip' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
    };
@endphp

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="h-1 w-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale"></div>

            <div class="p-5 lg:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Your account request</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            You have guest access until an administrator approves your sign-up.
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $tone['chip'] }}">
                        {{ $tone['label'] }}
                    </span>
                </div>

                @if ($status === 'rejected')
                    <div class="rounded-xl border border-error-200 bg-error-50 p-4 dark:border-error-500/30 dark:bg-error-500/10">
                        <p class="text-sm font-semibold text-error-700 dark:text-error-500">Why it was not approved</p>
                        <p class="mt-1 text-sm text-error-600 dark:text-error-400">
                            {{ $reason !== '' ? $reason : 'No reason was given.' }}
                        </p>

                        @if ($retryUrl)
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <a href="{{ $retryUrl }}"
                                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                    Correct my details and resubmit
                                </a>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Your previous answers are already filled in.
                                </span>
                            </div>
                        @endif

                        <p class="mt-3 text-xs font-medium text-error-600 dark:text-error-400">
                            {{ $attemptsLeft }} {{ $attemptsLeft === 1 ? 'attempt' : 'attempts' }} remaining — after that,
                            this guest account is closed.
                        </p>
                    </div>
                @elseif ($status === 'pending')
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/[0.02]">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            An administrator is reviewing your details. You'll get an email as soon as there's a
                            decision — nothing else is needed from you right now.
                        </p>
                    </div>
                @elseif ($status === 'none')
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/[0.02]">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            We don't have a sign-up request on file for this account.
                            @if ($retryUrl)
                                <a href="{{ $retryUrl }}" class="font-medium text-brand-500 hover:text-brand-600">Submit one now</a>.
                            @endif
                        </p>
                    </div>
                @endif

                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ($details as $label => $value)
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 text-sm dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="truncate font-medium text-gray-800 dark:text-white/90">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($organizationName !== '')
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 text-sm dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Organization</dt>
                            <dd class="truncate font-medium text-gray-800 dark:text-white/90">{{ $organizationName }}</dd>
                        </div>
                    @endif
                    @if ($submittedAt)
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 text-sm dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Submitted</dt>
                            <dd class="font-medium text-gray-800 dark:text-white/90">{{ $submittedAt->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                    @if ($decidedAt)
                        <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 text-sm dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Reviewed</dt>
                            <dd class="font-medium text-gray-800 dark:text-white/90">{{ $decidedAt->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm lg:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">What happens next</h2>
            <ol class="space-y-4">
                @foreach ([
                    ['Email confirmed', 'Your address is verified — that is what unlocked this page.', true],
                    ['Admin review', 'An administrator checks your details against your organization\'s records.', $status === 'approved'],
                    ['Full access', 'Once approved, this becomes an officer account with forms, events and documents.', $status === 'approved'],
                ] as [$stepTitle, $stepBody, $done])
                    <li class="flex gap-3">
                        <span @class([
                            'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-palette-lime text-gray-900' => $done,
                            'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' => ! $done,
                        ])>{{ $done ? '✓' : $loop->iteration }}</span>
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $stepTitle }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stepBody }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endsection
