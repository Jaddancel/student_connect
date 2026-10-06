@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Workplan" />

    <div class="space-y-6">

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- Document Header --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">WORKPLAN</p>
        </div>

        {{-- Info Row --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Organization</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $orgName }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Semester / School Year</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $semester->name }}</p>
                </div>
            </div>
        </div>

        {{-- Read-only Activities Table --}}
        @php
            $hasIncomplete = $plans->contains(fn($p) =>
                empty(trim((string) ($p->resources_needed ?? ''))) || empty($p->persons_responsible ?? [])
            );
        @endphp

        @if ($hasIncomplete)
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                <p class="font-semibold">PDF generation is blocked.</p>
                <p class="mt-0.5 font-normal">One or more plans are missing required fields (<span class="font-semibold">Resources Needed</span> and/or <span class="font-semibold">Persons Responsible</span>). Ask the responsible officers to revise those plans before generating.</p>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Planned Activities</h3>

            @if ($plans->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">No approved plans were found for this semester.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400 w-2/5">Activity / Title</th>
                                <th class="pb-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400 w-1/6">Target Date</th>
                                <th class="pb-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Resources Needed <span class="text-error-500">*</span>
                                </th>
                                <th class="pb-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Person/s Responsible <span class="text-error-500">*</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($plans as $plan)
                                @php
                                    $names = collect($plan->persons_responsible ?? [])
                                        ->map(fn($id) => $personNames[$id] ?? null)
                                        ->filter()
                                        ->implode(', ');
                                    $missingResources = empty(trim((string) ($plan->resources_needed ?? '')));
                                    $missingPersons = empty($plan->persons_responsible ?? []);
                                @endphp
                                <tr class="{{ ($missingResources || $missingPersons) ? 'bg-error-50/30 dark:bg-error-500/5' : '' }}">
                                    <td class="py-3 pr-4 font-medium text-gray-800 dark:text-white/80">{{ $plan->title }}</td>
                                    <td class="py-3 pr-4 text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($plan->target_date)->format('M d, Y') }}</td>
                                    <td class="py-3 pr-4 {{ $missingResources ? 'text-error-500 font-medium' : 'text-gray-600 dark:text-gray-400' }}">
                                        {{ $plan->resources_needed ?: ($missingResources ? 'Required — missing' : '—') }}
                                    </td>
                                    <td class="py-3 {{ $missingPersons ? 'text-error-500 font-medium' : 'text-gray-600 dark:text-gray-400' }}">
                                        {{ $names ?: ($missingPersons ? 'Required — missing' : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Request state: approved with PDF ready --}}
        @if ($requestStatus === 'approved')
            <div class="rounded-2xl border border-success-200 bg-success-50 p-5 dark:border-success-500/30 dark:bg-success-500/10 lg:p-6">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-success-600 dark:text-success-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-success-700 dark:text-success-400">Workplan Request Approved</p>
                        <p class="mt-0.5 text-sm text-success-600 dark:text-success-500">Your workplan request has been approved by the admin. You can now download the generated PDF.</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <a href="{{ route('event-plans') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        Back to Event Plans
                    </a>
                    @if ($generatedDocument && $generatedDocument->pdf_path)
                        <a href="{{ route('workplan.download', $workplan->workplan_id) }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-success-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-success-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download PDF
                        </a>
                    @else
                        <span class="rounded-lg bg-gray-300 px-6 py-2.5 text-sm font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                            PDF not yet generated
                        </span>
                    @endif
                </div>
            </div>

        {{-- Request state: no request yet, pending, or rejected (show form) --}}
        @else
            @if ($requestStatus === 'rejected')
                <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    Your previous workplan request was rejected. You may revise and resubmit below.
                </div>
            @endif

            {{-- Submit is now the "New Workplan" system-function form: the officer
                 picks which approved events to include and signs there. --}}
            @php $workplanEnabled = \App\Forms\SystemFunction::enabled('new_workplan'); @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('event-plans') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        Back to Event Plans
                    </a>
                    @if (! $workplanEnabled)
                        <span class="text-sm text-gray-400" title="An admin must create a Workplan form in the Form Builder first.">
                            Workplan submission is not available yet.
                        </span>
                    @elseif ($hasIncomplete)
                        <button type="button" disabled
                            title="Some plans are missing required fields"
                            class="cursor-not-allowed rounded-lg bg-gray-300 px-6 py-2.5 text-sm font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-500">
                            Submit Workplan Request
                        </button>
                    @else
                        <a href="{{ route('functions.show', 'new_workplan') }}"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Submit Workplan Request
                        </a>
                    @endif
                </div>
            </div>
        @endif

    </div>
@endsection
