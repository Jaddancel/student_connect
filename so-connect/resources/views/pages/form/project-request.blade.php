@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Letter of Intent / Project Request" />

    <div class="space-y-6">
        @if (session('success'))
            <div
                class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div
            class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-center text-base font-bold uppercase tracking-wide text-gray-900 dark:text-white">Republic of the
                Philippines</h2>
            <p class="mt-0.5 text-center text-sm font-semibold text-gray-800 dark:text-white/90">TARLAC AGRICULTURAL
                UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="mt-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300">OFFICE OF STUDENT SERVICES AND
                DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="mt-3 text-center text-base font-bold uppercase tracking-widest text-gray-900 dark:text-white">Letter
                of Intent / Project Request</p>
        </div>

        <form action="{{ route('project-request.store') }}" method="POST" class="space-y-6">
            @csrf

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if ($organizations->count() > 1)
                            <select name="organization_id"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('organization_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select organization</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->organization_id }}" @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" value="{{ $organizations->first()?->organization_name ?? '' }}"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id"
                                value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Project Details</h3>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="projectTitle" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Project Title <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="projectTitle" name="projectTitle" value="{{ old('projectTitle') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('projectTitle') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="Title of the project or letter" />
                    </div>

                    <div>
                        <label for="natureOfProject"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nature of Project <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="natureOfProject" name="natureOfProject"
                            value="{{ old('natureOfProject') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('natureOfProject') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="e.g. Outreach, Research, Community Service" />
                    </div>

                    <div>
                        <label for="projectArea" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Project Area <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="projectArea" name="projectArea" value="{{ old('projectArea') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('projectArea') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="e.g. Campus, Barangay, Municipality" />
                    </div>

                    <div>
                        <label for="letterOfIntent"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Letter of Intent <span class="text-error-500">*</span>
                        </label>
                        <textarea id="letterOfIntent" name="letterOfIntent" rows="10"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border {{ $errors->has('letterOfIntent') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="Write the intent of the project here">{{ old('letterOfIntent') }}</textarea>
                    </div>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit &amp; Generate PDF
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
