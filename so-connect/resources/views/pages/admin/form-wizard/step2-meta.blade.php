@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Form" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="2" :total="5" :labels="[1 => 'Upload', 2 => 'Details', 3 => 'AI Review', 4 => 'Revise', 5 => 'Confirm']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Step 2 — Form Details</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Give your new form a name and an optional purpose description.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.form-wizard.discard') }}"
                      onsubmit="return confirm('Discard this draft form?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                        Discard
                    </button>
                </form>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.form-wizard.meta.save') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6 space-y-5">
                <div>
                    <label for="form_title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Form Title <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="form_title" name="form_title"
                           value="{{ old('form_title', $form->name !== 'New Form (Draft)' ? $form->name : '') }}"
                           placeholder="e.g. Application for Organization Recognition"
                           class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 @error('form_title') border-error-400 @enderror" />
                    @error('form_title')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="form_purpose" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Purpose / Description <span class="text-gray-400 text-xs">(optional)</span>
                    </label>
                    <textarea id="form_purpose" name="form_purpose" rows="3"
                              placeholder="Briefly describe what this form is used for…"
                              class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 @error('form_purpose') border-error-400 @enderror">{{ old('form_purpose', $form->description_text) }}</textarea>
                    @error('form_purpose')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Next: AI Review →
                    </button>
                </div>
            </div>
        </form>

    </div>
@endsection
