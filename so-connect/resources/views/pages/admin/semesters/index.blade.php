@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Semester Management" />

    <div class="space-y-6">

        @if ($semesterWarning)
            @php
                $daysLeft = $semesterWarning['days_left'];
                $semName  = $semesterWarning['semester']->name;
                $endLabel = $daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days");
            @endphp
            <div class="flex items-start gap-3 rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-500/40 dark:bg-warning-500/10">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.69354 3.3009C10.7274 1.56085 13.2726 1.56085 14.3065 3.3009L21.8385 16.2174C22.8533 17.9251 21.5998 20.0834 19.532 20.0834H4.46799C2.40016 20.0834 1.14672 17.9251 2.16148 16.2174L9.69354 3.3009ZM12 8.25C12.4142 8.25 12.75 8.58579 12.75 9V13C12.75 13.4142 12.4142 13.75 12 13.75C11.5858 13.75 11.25 13.4142 11.25 13V9C11.25 8.58579 11.5858 8.25 12 8.25ZM12 16C12.5523 16 13 15.5523 13 15C13 14.4477 12.5523 14 12 14C11.4477 14 11 14.4477 11 15C11 15.5523 11.4477 16 12 16Z" fill="currentColor"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">
                        Preparation period ending {{ $endLabel }}
                    </p>
                    <p class="mt-0.5 text-sm text-warning-700 dark:text-warning-400">
                        The preparation period for <strong>{{ $semName }}</strong> closes {{ $endLabel }}. Once both semesters for this school year have concluded, you can start the next school year.
                    </p>
                </div>
            </div>
        @endif

        @if ($canStartNewYear && $semesters->isNotEmpty())
            <div class="flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-500/30 dark:bg-brand-500/10">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm text-brand-700 dark:text-brand-300">
                    Both semesters for the current school year have concluded. You can now start the next school year.
                </p>
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Start New School Year Form --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
            x-data="{ open: {{ $canStartNewYear ? 'false' : 'false' }} }">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Semesters</h3>
                @if ($canStartNewYear)
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Start New School Year
                    </button>
                @endif
            </div>

            @if ($canStartNewYear)
                <div x-show="open" x-cloak class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                        Set the start dates for both semesters. Names will be generated automatically based on the school year.
                    </p>
                    <form method="POST" action="{{ route('admin.semesters.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">1st Semester Start Date <span class="text-error-500">*</span></label>
                            <x-form.date-picker name="starts_at" placeholder="Select 1st sem start" id="new-semester-starts-at" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">2nd Semester Start Date <span class="text-error-500">*</span></label>
                            <x-form.date-picker name="second_starts_at" placeholder="Select 2nd sem start" id="new-semester-second-starts-at" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Preparation Days <span class="text-error-500">*</span></label>
                            <input type="number" name="vacation_days" value="30" min="1" max="365"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div class="flex justify-end sm:col-span-3">
                            <button type="submit"
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                Create School Year
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- Semesters Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if ($semesters->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No semesters configured yet. Add one above.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Semester</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Starts</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Preparation Period</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Prep. Days</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($semesters as $row)
                                @php $s = $row['model']; @endphp
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $s->name }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $s->starts_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                        {{ $row['active_start']->format('M d') }} – {{ $row['active_end']->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $s->vacation_days }}</td>
                                    <td class="px-6 py-4">
                                        @if ($row['is_active'])
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Preparation Period</span>
                                        @elseif ($s->starts_at->isFuture())
                                            <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Upcoming</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Past</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.semesters.edit', $s->semester_id) }}"
                                            class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
@endsection
