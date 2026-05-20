@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Edit Semester" />

    <div class="space-y-6">

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="mb-5 text-base font-semibold text-gray-800 dark:text-white/90">Edit Semester</h3>

            <form method="POST" action="{{ route('admin.semesters.update', $semester->semester_id) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Semester Name</label>
                    <input type="text" value="{{ $semester->name }}" disabled
                        class="h-11 w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                    <p class="mt-1 text-xs text-gray-400">Semester names are generated automatically.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Start Date <span class="text-error-500">*</span></label>
                    @if ($has_finalized_workplan)
                        <input type="text" value="{{ $semester->starts_at->format('M d, Y') }}" disabled
                            class="h-11 w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <p class="mt-1 text-xs text-gray-400">Cannot change — a workplan for this semester has been finalized.</p>
                    @else
                        <x-form.date-picker name="starts_at" id="edit-semester-starts-at"
                            :defaultDate="old('starts_at', $semester->starts_at->format('Y-m-d'))"
                            placeholder="Select start date" />
                    @endif
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Vacation Days <span class="text-error-500">*</span></label>
                    <input type="number" name="vacation_days" min="1" max="365"
                        value="{{ old('vacation_days', $semester->vacation_days) }}"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    <p class="mt-1 text-xs text-gray-400">Number of days before the start date that make up the preparation period for officers.</p>
                </div>

                <div class="flex items-center gap-3 sm:col-span-2">
                    <a href="{{ route('admin.semesters.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        Cancel
                    </a>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

    </div>
@endsection
