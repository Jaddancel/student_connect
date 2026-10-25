@extends(in_array((string) $form->system_function, [
    \App\Forms\SystemFunction::SIGN_UP,
    \App\Forms\SystemFunction::NEW_ORGANIZATION_REGISTRATION,
], true) ? 'layouts.fullscreen-layout' : 'layouts.app')

@php
    /** @var \App\Models\Form $form */
    /** @var \Illuminate\Support\Collection $fields */
    $preview = $preview ?? false;
    $isSignup = (string) $form->system_function === \App\Forms\SystemFunction::SIGN_UP;
    $isNewOrganization = (string) $form->system_function === \App\Forms\SystemFunction::NEW_ORGANIZATION_REGISTRATION;
    $isAccreditation = (string) $form->system_function === \App\Forms\SystemFunction::ORG_ACCREDITATION;
    $isPublicRegistration = $isSignup || $isNewOrganization;
    $applicationSubmitted = $isNewOrganization && session()->has('success');
@endphp

@section('content')
    @if ($isPublicRegistration)
        <main class="min-h-screen bg-slate-50 pb-12 dark:bg-gray-950">
            <header class="border-b border-gray-200 bg-white/95 dark:border-gray-800 dark:bg-gray-900/95">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 text-gray-900 dark:text-white">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1a3c2e] text-xs font-bold text-amber-300 shadow-sm">SO</span>
                        <span><strong class="block text-sm leading-4">SO Connect</strong><small class="text-xs text-gray-500 dark:text-gray-400">Tarlac Agricultural University</small></span>
                    </a>
                    <div class="flex items-center gap-2">
                        @if ($isNewOrganization)
                            <a href="{{ route('home') }}" class="hidden rounded-xl px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-[#2d6a4f] sm:block dark:text-gray-400">Back to home</a>
                        @endif
                        <a href="{{ route('login') }}" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-[#2d6a4f] hover:text-[#2d6a4f] dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Sign in</a>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
                @if ($isSignup)
                    <div class="mb-7 max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#2d6a4f] dark:text-emerald-400">Student officer registration</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-4xl">Create your officer account</h1>
                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300 sm:text-base">Start by scanning both sides of your student ID. We will use the scan to reduce manual typing, then you can review every detail before submission.</p>
                    </div>
                @else
                    @include('pages.form.partials.new-organization-hero')
                @endif
    @else
        <x-common.page-breadcrumb :pageTitle="$form->name" />
    @endif

    <div class="{{ $isSignup ? 'mx-auto max-w-5xl' : ($isNewOrganization ? 'mx-auto max-w-6xl' : '') }} space-y-6"
        @if ($isAccreditation)
            x-data="{ accreditationStarted: {{ $errors->any() ? 'true' : 'false' }} }"
            @accreditation-started="accreditationStarted = true"
        @endif>
        @if ($isAccreditation)
            <div x-show="!accreditationStarted" x-cloak data-accreditation-overview>
                @include('pages.form.partials.accreditation-overview', [
                    'accreditationCycle' => $accreditationCycle ?? null,
                ])
            </div>
        @endif

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

        @if (session('success') && ! $isNewOrganization)
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($applicationSubmitted)
            @include('pages.form.partials.new-organization-success')
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-error-200 bg-error-50 p-4 text-error-700 shadow-sm dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400" role="alert" tabindex="-1" id="form-errors">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-error-100 dark:bg-error-500/20" aria-hidden="true">!</span>
                    <div>
                        <h2 class="text-sm font-semibold">Please check the highlighted information</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm font-medium">
                            @foreach ($errors->getMessages() as $errorKey => $messages)
                                @foreach ($messages as $message)
                                    <li>
                                        @if ($fields->contains('field_key', $errorKey))
                                            <a href="#{{ $errorKey }}" class="underline decoration-error-300 underline-offset-2 hover:decoration-error-600">{{ $message }}</a>
                                        @else
                                            {{ $message }}
                                        @endif
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if (! empty($afterEvent))
            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">After event report for</p>
                <h3 class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90">{{ $afterEvent['name'] }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $afterEvent['organization_name'] }}
                    · Finished {{ $afterEvent['finished_at']->format('F j, Y g:i A') }}
                    @if ($afterEvent['location'] !== '')
                        · {{ $afterEvent['location'] }}
                    @endif
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Details from the event's original New Event form are carried into the printed report automatically.
                    @if ($afterEvent['deadline'])
                        This report must be filed by {{ $afterEvent['deadline']->format('F j, Y') }}, when the current semester ends.
                    @else
                        This report cannot be filed after the current semester ends.
                    @endif
                </p>
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

        @php $limitReached = ! $preview && ($submissionLimit['reached'] ?? false); @endphp

        @if ($applicationSubmitted)
            {{-- Keep the confirmation focused: a second application with the same
                 name or founder would only be rejected as a duplicate. --}}
        @elseif ($limitReached)
            <div class="rounded-2xl border border-error-200 bg-error-50 p-5 dark:border-error-500/30 dark:bg-error-500/10">
                <h3 class="text-sm font-semibold text-error-700 dark:text-error-400">Submissions closed for this semester</h3>
                <p class="mt-1 text-sm text-error-700 dark:text-error-400">{{ \App\Forms\SemesterSubmissionLimit::blockedMessage($form, $submissionLimit) }}</p>
            </div>
        @else
            @if (! $preview && ($submissionLimit ?? null))
                <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    {{ $submissionLimit['remaining'] }} of {{ $submissionLimit['limit'] }} accepted {{ $submissionLimit['limit'] === 1 ? 'submission' : 'submissions' }} remaining this semester.
                </p>
            @endif

            @if (! $isAccreditation && ! empty($accreditationChecklist ?? []))
                <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">Required attachments checklist</p>
                            <h3 class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90">Complete all documents before submission</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">PDF, JPG, PNG, or HEIC files are accepted. Every item below is mandatory.</p>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-600 shadow-sm dark:bg-gray-900 dark:text-brand-300">
                            {{ count($accreditationChecklist) }} required
                        </span>
                    </div>
                    <ol class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($accreditationChecklist as $index => $item)
                            <li>
                                <a href="#{{ $item['key'] }}" class="flex items-center gap-3 rounded-lg border border-brand-100 bg-white px-3 py-2 text-sm text-gray-700 transition hover:border-brand-300 dark:border-brand-500/20 dark:bg-gray-900 dark:text-gray-300">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-600 dark:bg-brand-500/20 dark:text-brand-300">{{ $index + 1 }}</span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <div class="{{ $isNewOrganization ? 'grid items-start gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]' : '' }}">
                @if ($isNewOrganization)
                    @include('pages.form.partials.new-organization-steps')
                @endif
                <div class="min-w-0">
                    @if ($isNewOrganization)
                        @include('components.form.new-organization-wizard', [
                            'form' => $form,
                            'fields' => $fields,
                            'prefill' => $prefill ?? [],
                            'conditions' => $conditions ?? [],
                            'advisers' => $advisers ?? [],
                            'special' => $special ?? [],
                            'hidden' => $hidden ?? [],
                            'preview' => $preview,
                        ])
                    @elseif ($isAccreditation)
                        @include('components.form.accreditation-wizard', [
                            'form' => $form,
                            'fields' => $fields,
                            'prefill' => $prefill ?? [],
                            'conditions' => $conditions ?? [],
                            'advisers' => $advisers ?? [],
                            'special' => $special ?? [],
                            'hidden' => $hidden ?? [],
                            'preview' => $preview,
                            'accreditationChecklist' => $accreditationChecklist ?? [],
                            'accreditationCycle' => $accreditationCycle ?? null,
                        ])
                    @else
                        @include('components.form.builder-form', [
                            'form' => $form,
                            'fields' => $fields,
                            'prefill' => $prefill ?? [],
                            'conditions' => $conditions ?? [],
                            'advisers' => $advisers ?? [],
                            'special' => $special ?? [],
                            'hidden' => $hidden ?? [],
                            'preview' => $preview,
                            'isSignup' => $isSignup,
                            'manualEnabled' => ! $isPublicRegistration,
                            'submitLabel' => $isSignup ? 'Submit registration' : 'Submit for approval',
                        ])
                    @endif
                </div>
            </div>
        @endif
    </div>
    @if ($isPublicRegistration)
            </div>
        </main>
    @endif
@endsection
