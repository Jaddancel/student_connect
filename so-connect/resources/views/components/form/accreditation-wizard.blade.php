@php
    use App\Forms\FieldType;

    $preview = $preview ?? false;
    $prefill = $prefill ?? [];
    $hidden = $hidden ?? [];
    $advisers = $advisers ?? [];
    $special = $special ?? [];
    $cycle = $accreditationCycle ?? [];
    $deadline = $cycle['deadline'] ?? null;
    $documentTypes = [FieldType::FILE, FieldType::IMAGE, FieldType::MULTI_IMAGE];
    $displayFields = collect($fields)
        ->reject(fn ($field) => FieldType::isPresentational($field->field_type))
        ->reject(fn ($field) => (string) $field->field_key === 'recognition_type')
        ->values();
    $documentFields = $displayFields->filter(fn ($field) => in_array($field->field_type, $documentTypes, true))->values();
    $recordFields = $displayFields->reject(fn ($field) => in_array($field->field_type, $documentTypes, true))->values();
    $requiredDocuments = $documentFields->filter(fn ($field) => (bool) $field->is_required)->values();
    $fieldSteps = collect()
        ->merge($recordFields->mapWithKeys(fn ($field) => [$field->field_key => 1]))
        ->merge($documentFields->mapWithKeys(fn ($field) => [$field->field_key => 2]));
    $errorStep = collect($errors->keys())->map(function ($key) use ($fieldSteps) {
        $baseKey = explode('.', (string) $key, 2)[0];

        return (int) ($fieldSteps[$key] ?? $fieldSteps[$baseKey] ?? 1);
    })->min();
    $initialStep = $errors->any() ? max(1, (int) $errorStep) : 0;
    $reviewFields = $displayFields->map(fn ($field) => [
        'key' => (string) $field->field_key,
        'label' => (string) $field->field_label,
        'document' => in_array($field->field_type, $documentTypes, true),
    ])->values();
@endphp

<form action="{{ $preview ? '#' : route('forms.render.submit', $form->route_name) }}" method="POST" enctype="multipart/form-data"
    class="space-y-5"
    x-data="formConditions({
        conditions: {{ \App\Support\JsLiteral::from($conditions ?? []) }},
        applicationStep: {{ $initialStep }},
        applicationSteps: 3,
    })"
    @input="track($event)" @change="track($event)" @submit.prevent="submitApplication()" :aria-busy="submitting.toString()"
    @if ($preview) onsubmit="return false;" @endif>
    @csrf
    @foreach ($hidden as $hiddenName => $hiddenValue)
        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}" />
    @endforeach

    <nav class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Accreditation application progress">
        <ol class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-800">
            @foreach ([1 => 'Organization records', 2 => 'Requirements', 3 => 'Review'] as $step => $label)
                <li class="px-2 py-3 text-center sm:px-4">
                    <button type="button" @click="goToApplicationStep({{ $step }})" class="w-full" :disabled="applicationStep === 0">
                        <span class="mx-auto flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition"
                            :class="applicationStep === {{ $step }} ? 'bg-[#1d513d] text-white ring-4 ring-emerald-100 dark:bg-emerald-600 dark:ring-emerald-500/10' : (applicationMaxStep > {{ $step }} ? 'bg-[#e9f5cf] text-[#35631f] dark:bg-lime-400/15 dark:text-lime-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-800')">{{ $step }}</span>
                        <span class="mt-1.5 hidden text-xs font-semibold sm:block" :class="applicationStep === {{ $step }} ? 'text-[#1d513d] dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <section x-show="applicationStep === 0" x-cloak data-application-panel="0"
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 bg-gradient-to-br from-emerald-50 to-lime-50 px-6 py-7 dark:border-gray-700 dark:from-emerald-950/60 dark:to-gray-900 sm:px-8">
            <span class="inline-flex rounded-full border border-emerald-100 bg-white px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-[#2d6a4f] shadow-sm dark:border-emerald-500/20 dark:bg-gray-950 dark:text-emerald-300">Prepare for accreditation</span>
            <h2 class="mt-4 text-2xl font-semibold text-gray-950 dark:text-white">Have your updated records ready</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                Confirm the official OSSD announcement and prepare the current organization information before starting. You can move between steps before submitting.
            </p>
        </div>

        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Records to review</h3>
                <ul class="mt-3 space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ([
                        'Current officers and membership directory',
                        'Faculty adviser and organization contact information',
                        'Election or officer-selection results under the Constitution and By-Laws',
                        'GPOA, proposed budget, accomplishment reports, and financial reports',
                    ] as $item)
                        <li class="flex gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#5f8f32] dark:text-lime-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-xs leading-5 text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    @if ($deadline)
                        Submit the complete application by <strong>{{ $deadline->format('F j, Y') }}</strong>.
                    @else
                        OSSD has not configured an accreditation deadline yet. Confirm the official application period before submitting.
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950/50">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Required uploads set by OSSD</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">This checklist comes directly from the administrator's current accreditation form.</p>
                    </div>
                    <span class="rounded-full border border-gray-100 bg-white px-2.5 py-1 text-xs font-bold text-[#2d6a4f] shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-emerald-300">{{ $requiredDocuments->count() }}</span>
                </div>
                @if ($requiredDocuments->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach ($requiredDocuments as $field)
                            <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><span class="h-1.5 w-1.5 rounded-full bg-[#7faa3d]"></span>{{ $field->field_label }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 rounded-lg border border-gray-100 bg-white px-3 py-2.5 text-xs leading-5 text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">No mandatory uploads are currently configured. Optional document fields may still appear in the requirements step.</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800 sm:px-8">
            <p class="text-xs text-gray-500 dark:text-gray-400">Only submit records for the organization selected in your account header.</p>
            <button type="button" @click="startAccreditation()" class="rounded-xl bg-[#1d513d] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#15382b] dark:bg-emerald-600 dark:hover:bg-emerald-500">Start accreditation →</button>
        </div>
    </section>

    @foreach ([
        1 => ['Update organization records', 'Confirm the organization, officers, adviser, membership, objectives, and current plans.', $recordFields],
        2 => ['Complete accreditation requirements', 'Upload the documents currently required by OSSD. Optional items may be left blank.', $documentFields],
    ] as $step => [$title, $description, $stepFields])
        <section x-show="applicationStep === {{ $step }}" x-cloak data-application-panel="{{ $step }}"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-7">
            <div class="mb-6 border-b border-gray-100 pb-5 dark:border-gray-800">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#2d6a4f] dark:text-emerald-400">Application step {{ $step }} of 3</p>
                <h2 class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>

            @if ($stepFields->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No fields are configured for this step.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">OSSD can update the accreditation requirements in the Form Builder.</p>
                </div>
            @else
                <div class="grid gap-x-5 gap-y-5 sm:grid-cols-2">
                    @foreach ($stepFields as $field)
                        @php
                            $clientRequired = (bool) $field->is_required
                                && ! ($field->field_type === FieldType::CHECKBOX && FieldType::optionValues((array) ($field->field_options ?? [])) === []);
                        @endphp
                        <div class="{{ in_array($field->field_type, [FieldType::TEXTAREA, FieldType::MULTI_IMAGE, FieldType::SIGNATURE], true) ? 'sm:col-span-2' : '' }}"
                            data-field-required="{{ $clientRequired ? 'true' : 'false' }}"
                            x-show="visible(@js($field->field_key))"
                            x-effect="toggleDisabled($el, visible(@js($field->field_key)))">
                            @include('components.form.fields.field', ['field' => $field, 'prefill' => $prefill, 'advisers' => $advisers, 'special' => $special])
                        </div>
                    @endforeach
                </div>
            @endif

            <p x-show="applicationStepError" x-cloak x-text="applicationStepError" class="mt-5 rounded-lg bg-error-50 px-3 py-2 text-sm font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400"></p>
            <div class="mt-7 flex items-center justify-between border-t border-gray-100 pt-5 dark:border-gray-800">
                <button type="button" @click="previousApplicationStep()" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">← Back</button>
                <button type="button" @click="nextApplicationStep()" class="rounded-xl bg-[#1d513d] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#15382b] dark:bg-emerald-600 dark:hover:bg-emerald-500">Continue →</button>
            </div>
        </section>
    @endforeach

    <section x-show="applicationStep === 3" x-cloak data-application-panel="3"
        class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-7">
        <div class="border-b border-gray-100 pb-5 dark:border-gray-800">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#2d6a4f] dark:text-emerald-400">Application step 3 of 3</p>
            <h2 class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">Review and submit</h2>
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Review the accreditation summary before sending it to OSSD for evaluation.</p>
        </div>

        <dl class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-gray-50/50 dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-950/40">
            @foreach ($reviewFields as $field)
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-4">
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $field['label'] }}</dt>
                    <dd class="break-words text-sm text-gray-800 dark:text-gray-200" x-text="reviewValue(@js($field['key']), @js($field['document']))"></dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-5 rounded-xl bg-emerald-50 p-4 dark:bg-emerald-500/10">
            <p class="text-sm font-semibold text-gray-800 dark:text-white">After submission</p>
            <p class="mt-1 text-xs leading-5 text-gray-600 dark:text-gray-300">OSSD will review prior activities, reports, financial accountability, membership, and policy compliance. Respond to any correction request before accreditation is issued.</p>
        </div>

        <p x-show="applicationStepError" x-cloak x-text="applicationStepError" class="mt-5 rounded-lg bg-error-50 px-3 py-2 text-sm font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400"></p>
        <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
            <button type="button" @click="previousApplicationStep()" class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">← Back</button>
            @if ($preview)
                <span class="text-sm text-gray-400">Submission is disabled in preview.</span>
            @else
                <div class="text-right">
                    <button type="submit" :disabled="submitting" class="inline-flex min-w-56 items-center justify-center gap-2 rounded-xl bg-[#1d513d] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#15382b] disabled:cursor-wait disabled:opacity-70 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"></path></svg>
                        <span x-text="submitting ? 'Submitting…' : 'Submit accreditation application'">Submit accreditation application</span>
                    </button>
                    <p x-show="submitting" x-cloak class="mt-2 text-xs text-gray-500 dark:text-gray-400">Please wait while your application is being sent to OSSD.</p>
                </div>
            @endif
        </div>
    </section>
</form>
