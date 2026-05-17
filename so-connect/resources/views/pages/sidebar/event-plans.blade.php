@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Event Plans" />

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="space-y-8">

        {{-- Pending Plans --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Pending Plans</h3>
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $grouped['pending']->count() }}
                </span>
            </div>

            @if ($grouped['pending']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No pending event plans.</p>
            @else
                <div class="space-y-4">
                    @foreach ($grouped['pending'] as $plan)
                        <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="pending" />
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Approved Plans --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Approved Plans</h3>
                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                    {{ $grouped['approved']->count() }}
                </span>
            </div>

            @if ($grouped['approved']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No approved event plans.</p>
            @else
                <div class="space-y-4">
                    @foreach ($grouped['approved'] as $plan)
                        <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="approved" />
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Rejected Plans --}}
        @if ($grouped['rejected']->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Rejected Plans</h3>
                <span class="inline-flex items-center rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">
                    {{ $grouped['rejected']->count() }}
                </span>
            </div>
            <div class="space-y-4">
                @foreach ($grouped['rejected'] as $plan)
                    <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="rejected" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Junked Plans --}}
        @if ($grouped['junked']->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Junked Plans</h3>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                    {{ $grouped['junked']->count() }}
                </span>
            </div>
            <div class="space-y-4">
                @foreach ($grouped['junked'] as $plan)
                    <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="junked" />
                @endforeach
            </div>
        </div>
        @endif

    </div>
@endsection
