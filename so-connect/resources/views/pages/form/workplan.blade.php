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

        {{-- Signatures + Generate --}}
        <form action="{{ route('workplan.generate', $workplan->workplan_id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Signatures</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- President --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Prepared by</p>

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $presidentName) }}"
                                placeholder="Full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('name')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="ml-1 text-xs font-normal text-gray-400">(JPG/PNG, max 2 MB)</span>
                            </label>
                            <label for="signature"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                                    </svg>
                                </template>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                    x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                                <input id="signature" name="signature" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name</p>
                        </div>
                    </div>

                    {{-- Adviser --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Noted by</p>

                        <div>
                            <label for="advisername" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser Name
                            </label>
                            <input type="text" id="advisername" name="advisername"
                                value="{{ old('advisername') }}"
                                placeholder="Faculty adviser's full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('event-plans') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        Back to Event Plans
                    </a>
                    @if ($hasIncomplete)
                        <button type="button" disabled
                            title="Some plans are missing required fields"
                            class="rounded-lg bg-gray-300 px-6 py-2.5 text-sm font-medium text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500">
                            Generate PDF
                        </button>
                    @else
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Generate PDF
                        </button>
                    @endif
                </div>
            </div>
        </form>

    </div>
@endsection
