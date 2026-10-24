{{--
    Renders a builder form's letterhead + field <form>. Shared by the standalone
    /forms/{route} page (pages/form/render.blade.php) and the calendar drawer,
    which embeds the new_event form inline.

    Expected data: $form, $fields, $prefill, $conditions, $advisers, $special,
    $hidden. Optional: $preview (bool), $submitLabel (string).
--}}
@php
    use Illuminate\Support\Facades\Storage;

    $preview = $preview ?? false;
    $prefill = $prefill ?? [];
    $hidden = $hidden ?? [];
    $advisers = $advisers ?? [];
    $special = $special ?? [];
    $submitLabel = $submitLabel ?? 'Submit for approval';
    $isSignup = $isSignup ?? ((string) ($form->system_function ?? '') === \App\Forms\SystemFunction::SIGN_UP);
    $isNewOrganization = $isNewOrganization ?? ((string) ($form->system_function ?? '') === \App\Forms\SystemFunction::NEW_ORGANIZATION_REGISTRATION);
    $hasIdScan = collect($fields)->contains(fn ($field) => $field->field_type === \App\Forms\FieldType::ID_SCAN);
    $registrationWizard = $isSignup && $hasIdScan;
    $registrationStep = $registrationWizard ? ($errors->any() ? 2 : 1) : 0;

    $layout = (array) ($form->layout ?? []);
    $header = (array) ($layout['header'] ?? []);
    $rows = $layout['rows'] ?? null;
    $fieldsByKey = collect($fields)->keyBy('field_key');

    if (! is_array($rows) || count($rows) === 0) {
        $rows = collect($fields)
            ->map(fn ($f) => ['columns' => [['span' => 12, 'fields' => [$f->field_key]]]])
            ->all();
    }

    $disk = (string) config('documents.disk', 'public');
    $headerAlign = in_array(($header['align'] ?? 'center'), ['left', 'center', 'right'], true) ? ($header['align'] ?? 'center') : 'center';
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$headerAlign];

    // Literal class strings so Tailwind's source scanner generates them.
    $spanClasses = [
        1 => 'md:col-span-1', 2 => 'md:col-span-2', 3 => 'md:col-span-3', 4 => 'md:col-span-4',
        5 => 'md:col-span-5', 6 => 'md:col-span-6', 7 => 'md:col-span-7', 8 => 'md:col-span-8',
        9 => 'md:col-span-9', 10 => 'md:col-span-10', 11 => 'md:col-span-11', 12 => 'md:col-span-12',
    ];
@endphp

{{-- Header / letterhead --}}
@if (! empty($header['image']) || ! empty($header['logo']) || ! empty($header['title']) || ! empty($header['subtitle']))
    <div class="{{ $alignClass }} mb-6 rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03]">
        @if (! empty($header['image']) && Storage::disk($disk)->exists($header['image']))
            <img src="{{ Storage::disk($disk)->url($header['image']) }}" alt="header" class="mx-auto max-h-40 w-full object-contain" />
        @else
            @if (! empty($header['logo']) && Storage::disk($disk)->exists($header['logo']))
                <img src="{{ Storage::disk($disk)->url($header['logo']) }}" alt="logo" class="mx-auto mb-2 max-h-20" />
            @endif
            @if (! empty($header['title']))
                <h2 class="text-base font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ $header['title'] }}</h2>
            @endif
            @if (! empty($header['subtitle']))
                <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $header['subtitle'] }}</p>
            @endif
        @endif
    </div>
@endif

<form action="{{ $preview ? '#' : route('forms.render.submit', $form->route_name) }}" method="POST" enctype="multipart/form-data" class="space-y-6"
    x-data="formConditions({ conditions: {{ \App\Support\JsLiteral::from($conditions ?? []) }}, registrationStep: {{ $registrationStep }} })"
    @input="track($event)" @change="track($event)" @submit="startSubmit()" @registration-step-changed="registrationStep = $event.detail.step" :aria-busy="submitting.toString()"
    @if ($preview) onsubmit="return false;" @endif>
    @csrf
    @foreach (($hidden ?? []) as $hiddenName => $hiddenValue)
        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}" />
    @endforeach

    @if (($form->route_name ?? '') === 'accomplishment-report')
        <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5 dark:border-brand-500/30 dark:bg-brand-500/5"
            x-data="{
                title: '', date: '', people: '', attachments: 0,
                refresh() {
                    const form = this.$root.closest('form');
                    this.title = document.getElementById('title')?.value || 'Untitled activity';
                    this.date = document.getElementById('date')?.value || 'No date yet';
                    this.people = document.getElementById('people')?.value || 'No participants entered yet';
                    this.attachments = [...form.querySelectorAll('input[type=file]')].reduce((n, input) => n + (input.files?.length || 0), 0)
                        + form.querySelectorAll('input[name$=\'_library[]\']').length;
                }
            }" x-init="$nextTick(() => refresh())" @input.window="refresh()" @change.window="refresh()">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-300">Live report preview</p>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-600 dark:bg-gray-900 dark:text-brand-300"><span x-text="attachments"></span> attachment(s)</span>
            </div>
            <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90" x-text="title"></h3>
            <p class="mt-1 text-sm text-gray-500" x-text="date"></p>
            <p class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300" x-text="people"></p>
        </div>
    @endif

    @if ($registrationWizard)
        <nav class="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="Registration progress">
            <ol class="grid grid-cols-2 gap-2">
                <li class="flex items-center gap-3 rounded-xl px-3 py-3 sm:px-5"
                    :class="registrationStep === 1 ? 'bg-[#1a3c2e] text-white' : 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="registrationStep === 1 ? 'bg-white/15 text-amber-300' : 'bg-success-100 text-success-700 dark:bg-success-500/20'">
                        <span x-show="registrationStep === 1">1</span>
                        <svg x-show="registrationStep === 2" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                    </span>
                    <span><span class="block text-sm font-semibold">Scan student ID</span><span class="hidden text-xs opacity-70 sm:block">Capture front and back</span></span>
                </li>
                <li class="flex items-center gap-3 rounded-xl px-3 py-3 sm:px-5"
                    :class="registrationStep === 2 ? 'bg-[#1a3c2e] text-white' : 'bg-gray-50 text-gray-400 dark:bg-white/[0.03]'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="registrationStep === 2 ? 'bg-white/15 text-amber-300' : 'bg-gray-200 text-gray-500 dark:bg-gray-800'">2</span>
                    <span><span class="block text-sm font-semibold">Details and signature</span><span class="hidden text-xs opacity-70 sm:block">Review, complete, submit</span></span>
                </li>
            </ol>
        </nav>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] lg:p-7">
        @if ($isSignup || $isNewOrganization)
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-5 dark:border-gray-800" x-show="{{ $registrationWizard ? 'registrationStep === 2' : 'true' }}" x-cloak>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $isNewOrganization ? 'Organization application' : 'Registration details' }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fields marked with <span class="font-semibold text-error-500">*</span> are required. {{ $isNewOrganization ? 'Upload clear, final copies of the required documents.' : 'Review all auto-filled details before submitting.' }}</p>
                </div>
                <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">{{ $isNewOrganization ? 'OSSD application' : 'Secure application' }}</span>
            </div>
        @endif
        <div class="{{ $isSignup ? 'grid gap-x-5 gap-y-6 md:grid-cols-2' : 'space-y-5' }}">
            @foreach ($rows as $row)
                @php
                    $rowHeader = trim((string) ($row['header'] ?? ''));
                    $rowText = trim((string) ($row['static_text'] ?? ''));
                    $rowFieldKeys = collect($row['columns'] ?? [])->flatMap(fn ($column) => $column['fields'] ?? []);
                    $rowFieldTypes = $rowFieldKeys->map(fn ($fieldKey) => $fieldsByKey[$fieldKey]->field_type ?? null)->filter();
                    $rowHasIdScan = $rowFieldTypes->contains(\App\Forms\FieldType::ID_SCAN);
                    $signupWideRow = $rowHeader !== '' || $rowText !== '' || $rowFieldTypes->contains(fn ($type) => in_array($type, [
                        \App\Forms\FieldType::HEADING,
                        \App\Forms\FieldType::STATIC_TEXT,
                        \App\Forms\FieldType::ID_SCAN,
                        \App\Forms\FieldType::SIGNATURE,
                    ], true));
                @endphp
                <div class="space-y-3 {{ $isSignup && $signupWideRow ? 'md:col-span-2' : '' }}"
                    @if ($registrationWizard && ! $rowHasIdScan) x-show="registrationStep === 2" x-cloak @endif>
                    @if ($rowHeader !== '')
                        <h3 class="mb-1 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">{{ $rowHeader }}</h3>
                    @endif
                    @if ($rowText !== '')
                        <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ $rowText }}</p>
                    @endif
                    <div class="grid grid-cols-12 gap-4">
                    @foreach (($row['columns'] ?? []) as $col)
                        @php $span = max(1, min(12, (int) ($col['span'] ?? 12))); @endphp
                        <div class="col-span-12 {{ $spanClasses[$span] }} space-y-4">
                            @foreach (($col['fields'] ?? []) as $fieldKey)
                                @php $field = $fieldsByKey[$fieldKey] ?? null; @endphp
                                @if ($field)
                                    {{-- Conditional visibility: hidden wrappers also disable
                                         their controls so the values stay out of the POST. --}}
                                    <div x-show="visible(@js($fieldKey))" x-cloak
                                        x-effect="toggleDisabled($el, visible(@js($fieldKey)))">
                                        @include('components.form.fields.field', ['field' => $field, 'prefill' => $prefill, 'advisers' => $advisers ?? [], 'special' => $special ?? []])
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
        @if ($registrationWizard) x-show="registrationStep === 2" x-cloak @endif>
        @if ($isSignup && ! $preview)
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-brand-50 p-4 dark:bg-brand-500/10">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-11a1 1 0 110-2 1 1 0 010 2zm-1 2a1 1 0 000 2v3a1 1 0 102 0v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90">What happens next?</p>
                    <p class="mt-1 text-xs leading-5 text-gray-600 dark:text-gray-300">We will email you to confirm your address. After confirmation, you can sign in and monitor the administrator's review of your officer application.</p>
                </div>
            </div>
        @endif
        @if ($isNewOrganization && ! $preview)
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-emerald-50 p-4 dark:bg-emerald-500/10">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#2d6a4f] dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-11a1 1 0 110-2 1 1 0 010 2zm-1 2a1 1 0 000 2v3a1 1 0 102 0v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90">What happens after submission?</p>
                    <p class="mt-1 text-xs leading-5 text-gray-600 dark:text-gray-300">OSSD will confirm receipt and screen the application. Submission does not yet authorize the organization to operate; wait for the official recognition document.</p>
                </div>
            </div>
        @endif
        <div class="flex flex-wrap items-center {{ ($isSignup || $isNewOrganization) ? 'justify-between' : 'justify-end' }} gap-3">
            @if ($preview)
                <span class="text-sm text-gray-400">Submission is disabled in preview.</span>
            @else
                @if (($manualEnabled ?? true) && ($form->route_name ?? ''))
                    <div x-data="manualFormStart({ startUrl: '{{ route('manual.start', $form->route_name) }}', csrf: '{{ csrf_token() }}' })"
                        class="mr-auto flex items-center gap-2">
                        <button type="button" @click="start()" :disabled="starting"
                            class="rounded-lg border border-brand-300 px-4 py-2.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50 disabled:opacity-60 dark:border-brand-500/40 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <span x-show="!starting">Start Manual Filling</span>
                            <span x-show="starting" x-cloak>Preparing…</span>
                        </button>
                        <span x-show="error" x-cloak x-text="error" class="text-xs text-error-600"></span>
                    </div>
                @endif
                @if ($isSignup || $isNewOrganization)
                    <p class="max-w-xl text-xs leading-5 text-gray-500 dark:text-gray-400">By submitting, you confirm that the information and uploaded documents are accurate.</p>
                @else
                    <button type="reset" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Clear</button>
                @endif
                <button type="submit" :disabled="submitting" class="inline-flex min-w-44 items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 disabled:cursor-wait disabled:opacity-70">
                    <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span x-text="submitting ? 'Submitting…' : @js($submitLabel)">{{ $submitLabel }}</span>
                </button>
            @endif
        </div>
    </div>
</form>
