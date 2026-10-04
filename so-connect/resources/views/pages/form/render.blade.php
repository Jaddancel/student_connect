@extends('layouts.app')

@php
    /** @var \App\Models\Form $form */
    /** @var \Illuminate\Support\Collection $fields */
    $preview = $preview ?? false;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$form->name" />

    <div class="space-y-6">
        @if ($preview)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                <span>Preview mode — this form is shown regardless of its published/active status. Submitting is disabled.</span>
                @if (($previewTemplates ?? collect())->isNotEmpty())
                    @foreach ($previewTemplates as $template)
                        <a href="{{ route('admin.form-builder.preview', ['form' => $form, 'document' => 1, 'template' => $template->getKey()]) }}" target="_blank"
                            class="rounded-lg border border-warning-300 px-3 py-1.5 text-xs font-semibold text-warning-700 transition hover:bg-warning-100 dark:border-warning-500/40 dark:text-warning-300">
                            Preview {{ $template->template_name ?: $form->name }}
                        </a>
                    @endforeach
                @elseif (! empty($form->pdf_template['html']))
                    <a href="{{ route('admin.form-builder.preview', ['form' => $form, 'document' => 1]) }}" target="_blank"
                        class="rounded-lg border border-warning-300 px-3 py-1.5 text-xs font-semibold text-warning-700 transition hover:bg-warning-100 dark:border-warning-500/40 dark:text-warning-300">Preview printed document</a>
                @endif
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- The submitter's latest requests for this form + decision state --}}
        @if (! $preview && ! empty($recentSubmissions ?? []))
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Your recent submissions</h3>
                <ul class="space-y-2">
                    @foreach ($recentSubmissions as $entry)
                        <li class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $entry['requested_at'] ?? '—' }}</span>
                            @if ($entry['status'] === 'pending')
                                <span class="rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">Awaiting admin approval</span>
                            @elseif ($entry['status'] === 'approved')
                                <span class="rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">Approved</span>
                                <a href="{{ route('documents.index') }}" class="text-xs font-medium text-brand-500 hover:text-brand-600">View documents →</a>
                            @else
                                <span class="rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-600 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                                @if ($entry['reason'] !== '')
                                    <span class="text-xs text-gray-500 dark:text-gray-400">— {{ $entry['reason'] }}</span>
                                @endif
                                <span class="text-xs text-gray-400">You can fill the form again below.</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('components.form.builder-form', [
            'form' => $form,
            'fields' => $fields,
            'prefill' => $prefill ?? [],
            'conditions' => $conditions ?? [],
            'advisers' => $advisers ?? [],
            'special' => $special ?? [],
            'hidden' => $hidden ?? [],
            'preview' => $preview,
        ])
    </div>
@endsection
