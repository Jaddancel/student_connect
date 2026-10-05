@php
    use App\Forms\FieldType;
    use App\Forms\OptionSource;

    /** @var \App\Models\Form\FormDescription $field */
    $type = $field->field_type;
    $key = $field->field_key;
    $opts = (array) ($field->field_options ?? []);
    $required = (bool) $field->is_required;
    $special = $special ?? [];
    // Fall back to the universal-field autofill value (from the user's profile)
    // when there's no old() input yet; a resubmit still wins via old().
    $prefill = $prefill ?? [];
    $old = old($key, $prefill[$key] ?? '');
    $placeholder = $field->placeholder_hint ?? '';
    $inputClass = 'dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 '
        . ($errors->has($key) ? 'border-error-500' : 'border-gray-300');
    // A sourced select/search draws its choices from a scoped OptionSource list
    // (resolved into $special['sources']); otherwise, from the field's own
    // hand-typed options.
    $sourceKey = OptionSource::forField($opts);
    $sourceEntry = $sourceKey !== null
        ? ($special['sources'][$key] ?? ['options' => [], 'searchable' => OptionSource::isSearchable($sourceKey)])
        : ['options' => [], 'searchable' => false];
    $pairs = $sourceKey !== null ? $sourceEntry['options'] : FieldType::optionPairs($opts);
@endphp

@if ($type === FieldType::ACTIVITY_TABLE)
    {{-- PDF-only: the approved-activity rows are snapshotted server-side at
         submit (see FormRenderController), so the web form shows nothing —
         not even a label. --}}
@elseif ($type === FieldType::HEADING)
    <h3 class="mb-1 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
        {{ $field->field_label }}
    </h3>
@elseif ($type === FieldType::STATIC_TEXT)
    <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ $opts['content'] ?? $field->field_label }}</p>
@else
    {{-- data-universal-key lets the ID-scan wizard find and fill whichever
         input is bound to a universal field (the signature case carries the
         marker on its own component root, where the events are handled). --}}
    <div @if ($field->universal_key && $type !== FieldType::SIGNATURE) data-universal-key="{{ $field->universal_key }}" @endif>
        <label for="{{ $key }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $field->field_label }}
            @if ($required)<span class="text-error-500">*</span>@endif
        </label>

        @if ($field->universal_key === 'adviser')
            {{-- "Advisers" universal field: a dropdown of the org's known advisers
                 that also accepts a new name (persisted on submit via a datalist). --}}
            <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}"
                list="adviser-list-{{ $key }}" placeholder="{{ $placeholder ?: 'Select or type an adviser' }}"
                class="{{ $inputClass }}" autocomplete="off" />
            <datalist id="adviser-list-{{ $key }}">
                @foreach (($advisers ?? []) as $adviserName)
                    <option value="{{ $adviserName }}"></option>
                @endforeach
            </datalist>
            <p class="mt-1 text-xs text-gray-400">Pick a previous adviser, or type a new name to add it.</p>
        @else
        @switch($type)
            @case(FieldType::TEXTAREA)
                <textarea id="{{ $key }}" name="{{ $key }}" rows="{{ (int) ($opts['rows'] ?? 5) }}"
                    placeholder="{{ $placeholder }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 {{ $errors->has($key) ? 'border-error-500' : 'border-gray-300' }}">{{ $old }}</textarea>
                @break

            @case(FieldType::SELECT)
                <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}">
                    <option value="">{{ $placeholder ?: 'Select an option' }}</option>
                    @foreach ($pairs as $p)
                        <option value="{{ $p['value'] }}" @selected($old === $p['value'])>{{ $p['label'] }}</option>
                    @endforeach
                </select>
                @break

            @case(FieldType::SEARCH)
                {{-- Typeahead combobox over a scoped OptionSource list: submits
                     the chosen entry's id via a hidden input while showing its
                     label. Better than a giant <select> for large sources. --}}
                <div x-data="searchSelectField({ options: {{ Illuminate\Support\Js::from($sourceEntry['options'] ?? []) }}, selected: @js((string) $old) })" class="relative">
                    <input type="hidden" name="{{ $key }}" :value="selected" />
                    <div class="relative">
                        <input type="text" x-model="query" @focus="open = true" @click="open = true"
                            @input="onInput()" @keydown.escape="open = false"
                            placeholder="{{ $placeholder ?: 'Search…' }}" autocomplete="off"
                            class="{{ $inputClass }}" />
                        <button type="button" x-show="selected" x-cloak @click="clear()"
                            class="absolute inset-y-0 right-2 my-auto h-5 text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <div x-show="open" x-cloak @click.outside="open = false"
                        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <template x-if="!filtered.length">
                            <p class="px-3 py-2 text-xs text-gray-400">No matches.</p>
                        </template>
                        <template x-for="opt in filtered" :key="opt.value">
                            <button type="button" @click="choose(opt)"
                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-brand-50 dark:text-gray-200 dark:hover:bg-white/[0.06]"
                                x-text="opt.label"></button>
                        </template>
                    </div>
                </div>
                @if (! count($pairs))
                    <p class="mt-1 text-xs text-warning-600 dark:text-orange-400">No entries are available to search yet.</p>
                @endif
                @break

            @case(FieldType::RADIO)
                <div class="flex flex-wrap gap-4 pt-1">
                    @foreach ($pairs as $p)
                        <label class="flex cursor-pointer items-center gap-2.5">
                            <input type="radio" name="{{ $key }}" value="{{ $p['value'] }}" @checked($old === $p['value'])
                                class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $p['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @break

            @case(FieldType::CHECKBOX)
                @php $oldArr = (array) old($key, []); @endphp
                @if (count($pairs))
                    <div class="flex flex-wrap gap-4 pt-1">
                        @foreach ($pairs as $p)
                            <label class="flex cursor-pointer items-center gap-2.5">
                                <input type="checkbox" name="{{ $key }}[]" value="{{ $p['value'] }}" @checked(in_array($p['value'], $oldArr, true))
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $p['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <label class="flex cursor-pointer items-center gap-2.5 pt-1">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($old)
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $field->field_label }}</span>
                    </label>
                @endif
                @break

            @case(FieldType::DATE)
                @php $nowDefault = (empty($old) && ! empty($opts['autofill_now'])) ? ", defaultDate: 'today'" : ''; @endphp
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'Select a date' }}"
                    x-data x-init="window.flatpickr && window.flatpickr($el, { dateFormat: 'Y-m-d'{!! $nowDefault !!} })"
                    class="{{ $inputClass }}" autocomplete="off" />
                @break

            @case(FieldType::TIME)
                @php $nowDefault = (empty($old) && ! empty($opts['autofill_now'])) ? ", defaultDate: new Date()" : ''; @endphp
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'Select a time' }}"
                    x-data x-init="window.flatpickr && window.flatpickr($el, { enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true{!! $nowDefault !!} })"
                    class="{{ $inputClass }}" autocomplete="off" />
                @break

            @case(FieldType::DATETIME)
                @php $nowDefault = (empty($old) && ! empty($opts['autofill_now'])) ? ", defaultDate: new Date()" : ''; @endphp
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'Select a date and time' }}"
                    x-data x-init="window.flatpickr && window.flatpickr($el, { enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true{!! $nowDefault !!} })"
                    class="{{ $inputClass }}" autocomplete="off" />
                @break

            @case(FieldType::NUMBER)
            @case(FieldType::AGE)
                <input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder }}"
                    @if (! empty($opts['calculate_from'])) data-calculate-from="{{ $opts['calculate_from'] }}" @endif
                    @isset($opts['min']) min="{{ $opts['min'] }}" @endisset
                    @isset($opts['max']) max="{{ $opts['max'] }}" @endisset
                    step="{{ $opts['step'] ?? ($type === FieldType::AGE ? '1' : 'any') }}"
                    class="{{ $inputClass }}" />
                @break

            @case(FieldType::EMAIL)
            @case(FieldType::NEW_OFFICER_EMAIL)
            @case(FieldType::NEW_PRESIDENT_EMAIL)
                <input type="email" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'name@example.com' }}"
                    @if (in_array($type, [FieldType::NEW_OFFICER_EMAIL, FieldType::NEW_PRESIDENT_EMAIL], true))
                        x-data="{ status: null, check() { if (! this.$el.value) { this.status = null; return; } fetch(@js(route('email-availability.check')) + '?email=' + encodeURIComponent(this.$el.value)).then(r => r.json()).then(d => this.status = d.registered ? 'registered' : 'available').catch(() => this.status = null); } }"
                        @blur="check()"
                    @endif
                    class="{{ $inputClass }}" />
                @if (in_array($type, [FieldType::NEW_OFFICER_EMAIL, FieldType::NEW_PRESIDENT_EMAIL], true))
                    <div class="mt-1 text-xs">
                        <template x-if="status === 'registered'"><p class="text-brand-600 dark:text-brand-400">This email is already registered.</p></template>
                        <template x-if="status === 'available'"><p class="text-success-600 dark:text-success-500">This email is available.</p></template>
                    </div>
                @endif
                @break

            @case(FieldType::IMAGE)
            @case(FieldType::FILE)
                @php
                    $isMultipleImage = FieldType::isMultiImage($type, $opts);
                    $maxFiles = (int) ($opts['max_files'] ?? 5);
                @endphp
                <div x-data="{ previews: [], previewUrls: [], error: '', clearPreviews() {
                        this.previewUrls.forEach((url) => URL.revokeObjectURL(url));
                        this.previewUrls = [];
                        this.previews = [];
                    }, updatePreview(event) {
                        const files = Array.from(event.target.files || []);
                        this.clearPreviews();
                        this.error = '';
                        if (@js($isMultipleImage) && files.length > @js($maxFiles)) {
                            this.error = `Choose up to ${@js($maxFiles)} images.`;
                            event.target.value = '';
                            return;
                        }
                        this.previewUrls = files
                            .filter((file) => file.type.startsWith('image/'))
                            .map((file) => URL.createObjectURL(file));
                        this.previews = this.previewUrls;
                    } }">
                    <input type="file" id="{{ $key }}" name="{{ $isMultipleImage ? $key.'[]' : $key }}"
                        accept="{{ FieldType::uploadAcceptAttribute($type, $opts) }}"
                        @if ($isMultipleImage) multiple @endif
                        @change="updatePreview($event)"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 dark:focus:border-brand-800 w-full rounded-lg border bg-transparent text-sm text-gray-500 file:mr-4 file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-brand-600 dark:border-gray-700 {{ $errors->has($key) ? 'border-error-500' : 'border-gray-300' }}" />
                    <p x-show="error" x-text="error" class="mt-1 text-xs text-error-500"></p>
                    <template x-if="previews.length">
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template x-for="(preview, index) in previews" :key="preview">
                                <button type="button" @click="$store.lightbox.show(preview, @js($field->field_label))"
                                    class="group block cursor-zoom-in overflow-hidden rounded-lg border border-gray-200 bg-white p-1 transition hover:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-gray-700"
                                    title="Click to view full size">
                                    <img :src="preview" :alt="@js($field->field_label).' preview '+(index + 1)"
                                        class="h-32 w-48 object-contain transition group-hover:scale-[1.02]" />
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Allowed: {{ strtoupper(implode(', ', array_diff(FieldType::effectiveUploadExtensions($type, $opts), ['jpg', 'heif']))) }}
                    @if ($isMultipleImage)
                        ; up to {{ $maxFiles }} images
                    @endif
                </p>
                @break

            @case(FieldType::SIGNATURE)
                @php
                    // A saved profile signature arrives as a stored path via prefill;
                    // an uploaded/scanned signature arrives as a data-URL via old().
                    $savedSignaturePath = (is_string($old) && $old !== '' && ! str_starts_with($old, 'data:')) ? $old : null;
                    $savedSignatureUrl = $savedSignaturePath
                        ? \Illuminate\Support\Facades\Storage::disk(\App\Support\SignatureImage::disk())->url($savedSignaturePath)
                        : null;
                @endphp
                <div x-data="signatureImageField({
                        verifyUrl: @js(auth()->check() ? route('signature.verify') : null),
                        enrollUrl: @js(auth()->check() ? route('signature.enroll') : null),
                        compare: @js(FieldType::signatureExpectsMatch($opts)),
                        savedUrl: @js($savedSignatureUrl),
                        savedPath: @js($savedSignaturePath),
                    })" class="space-y-2"
                    {{-- data-signature-field lets the ID-scan wizard fill this box with a
                         scanned signature even when no universal key is bound — it only
                         does so when the form has exactly one signature field. --}}
                    data-signature-field
                    @if ($field->universal_key) data-universal-key="{{ $field->universal_key }}" @endif
                    @signature-set="fromDataUrl($event.detail.dataUrl)"
                    @signature-clear="clearFromScan()">

                    <input type="hidden" name="{{ $key }}" x-ref="input" value="{{ $old }}" />

                    {{-- Preview of the current signature (saved, uploaded or scanned) --}}
                    <template x-if="preview">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-white/[0.03]">
                            <button type="button" @click="$store.lightbox.show(preview, 'Signature')"
                                class="cursor-zoom-in rounded bg-white p-1 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                                title="Click to view full size">
                                <img :src="preview" alt="Signature" class="h-14 w-auto object-contain" />
                            </button>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="usingSaved ? 'Using your saved signature.' : 'Signature ready.'"></p>
                        </div>
                    </template>

                    {{-- Upload a photo of a signature; only the ink is kept --}}
                    <label class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 px-4 py-3 text-center text-xs font-medium text-gray-500 transition hover:border-brand-400 hover:text-brand-600 dark:border-gray-700">
                        <span x-text="preview ? 'Replace with another image' : 'Upload a photo of your signature (JPEG/PNG)'"></span>
                        <input type="file" accept="image/jpeg,image/png,image/heic" class="hidden" @change="onUpload($event)" />
                    </label>
                    <p x-cloak x-show="extractError" x-text="extractError"
                        class="rounded-lg bg-error-50 px-3 py-2 text-xs text-error-600 dark:bg-error-500/15 dark:text-error-500"></p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" x-show="preview" @click="clear()"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                            Clear
                        </button>
                        <button type="button" x-show="savedPath && !usingSaved" x-cloak @click="useSaved()"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                            Use saved signature
                        </button>
                        {{-- Advisory recognition badge; never blocks submission. --}}
                        <span x-cloak x-show="verifyState === 'checking'" class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            Checking signature…
                        </span>
                        <span x-cloak x-show="verifyState === 'recognized'" class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <span>Signature recognized<template x-if="matchedName"><span> as <span x-text="matchedName"></span></span></template></span>
                        </span>
                        {{-- Compare-mode fields enforce the match server-side; the
                             badge is advisory and never offers to name the signer. --}}
                        <span x-cloak x-show="verifyState === 'not_recognized' && compare" class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">
                            Not recognized as the expected signer
                        </span>
                        <span x-cloak x-show="verifyState === 'not_recognized' && !compare && !savedName" class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">
                            Not recognized in the system — you can still submit
                        </span>
                        <span x-cloak x-show="savedName" class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                            Saved as <span x-text="savedName"></span>
                        </span>
                        <span x-cloak x-show="verifyState === 'no_signatures'" class="text-xs text-gray-400">
                            No saved signatures to compare against yet
                        </span>
                        <span x-cloak x-show="verifyState === 'unavailable'" class="text-xs text-gray-400">
                            Signature recognition is unavailable right now
                        </span>
                    </div>

                    {{-- Normal-mode: name an unrecognized signature so it's saved as a
                         profile and recognized next time. Hidden in Compare mode. --}}
                    <div x-cloak x-show="enrollUrl && !compare && verifyState === 'not_recognized' && !savedName"
                        class="flex flex-wrap items-end gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-white/[0.03]">
                        <div class="flex-1">
                            <label class="mb-1 block text-[11px] text-gray-500 dark:text-gray-400">Whose signature is this?</label>
                            <input type="text" x-model="ownerName" placeholder="Full name"
                                @keydown.enter.prevent="saveOwnerName()"
                                class="h-8 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <button type="button" @click="saveOwnerName()" :disabled="saveState === 'saving' || !ownerName.trim()"
                            class="h-8 rounded-lg bg-brand-500 px-3 text-xs font-medium text-white disabled:opacity-50">
                            <span x-text="saveState === 'saving' ? 'Saving…' : 'Save'"></span>
                        </button>
                        <p x-cloak x-show="saveState === 'error'" class="w-full text-[11px] text-error-500" x-text="saveError"></p>
                    </div>
                </div>
                @break

            @case(FieldType::ORG_SELECT)
                @php $orgs = $special['organizations'] ?? []; @endphp
                @if (count($orgs) === 1)
                    <input type="hidden" name="{{ $key }}" value="{{ $orgs[0]['id'] }}" />
                    <input type="text" value="{{ $orgs[0]['name'] }}" readonly
                        class="{{ $inputClass }} bg-gray-50 dark:bg-white/[0.02]" />
                @else
                    <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}">
                        <option value="">{{ $placeholder ?: 'Select your organization' }}</option>
                        @foreach ($orgs as $org)
                            <option value="{{ $org['id'] }}" @selected((string) $old === (string) $org['id'])>{{ $org['name'] }}</option>
                        @endforeach
                    </select>
                @endif
                @break

            @case(FieldType::POSITION_SELECT)
                @php $positions = FieldType::optionValues($opts) ?: FieldType::POSITION_OPTIONS; @endphp
                <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}">
                    <option value="">{{ $placeholder ?: 'Select a position' }}</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position }}" @selected($old === $position)>{{ $position }}</option>
                    @endforeach
                </select>
                @break

            @case(FieldType::ORGANIZATION_TYPE_SELECT)
                <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}">
                    <option value="">{{ $placeholder ?: 'Select an organization type' }}</option>
                    @foreach (\App\Enums\OrganizationType::options() as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected((string) $old === (string) $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                @break

            @case(FieldType::PASSWORD)
                <input type="password" id="{{ $key }}" name="{{ $key }}"
                    placeholder="{{ $placeholder ?: 'Choose a password' }}" autocomplete="new-password"
                    class="{{ $inputClass }}" />
                <input type="password" id="{{ $key }}_confirmation" name="{{ $key }}_confirmation"
                    placeholder="Confirm password" autocomplete="new-password"
                    class="{{ $inputClass }} mt-2" />
                @break

            @case(FieldType::ID_SCAN)
                @include('components.form.fields.id-scan', ['field' => $field, 'key' => $key, 'old' => $old, 'placeholder' => $placeholder, 'inputClass' => $inputClass, 'special' => $special ?? []])
                @break

            @case(FieldType::WAIVER_SCAN)
                @include('components.form.fields.waiver-scan', ['field' => $field, 'key' => $key, 'old' => $old, 'inputClass' => $inputClass, 'special' => $special ?? []])
                @break

            @case(FieldType::EVENT_SELECT)
                @php $events = $special['events'] ?? []; $autofillMap = (array) ($opts['autofill_map'] ?? []); @endphp
                <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}"
                    x-data
                    @change="
                        const opt = $el.selectedOptions[0];
                        if (!opt) return;
                        const map = {{ Illuminate\Support\Js::from($autofillMap) }};
                        for (const [attr, target] of Object.entries(map)) {
                            const val = opt.dataset[attr];
                            if (val === undefined) continue;
                            const el = document.querySelector('[name=&quot;' + target + '&quot;]');
                            if (el && !el.value) { el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); }
                        }
                    ">
                    <option value="">{{ $placeholder ?: 'Select an approved event' }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event['id'] }}" @selected((string) $old === (string) $event['id'])
                            data-title="{{ $event['title'] }}" data-date="{{ $event['date'] }}" data-people="{{ $event['people'] }}"
                            data-activity_type="{{ ($event['activity_types'][0] ?? '') }}">{{ $event['title'] }}</option>
                    @endforeach
                </select>
                @if (! count($events))
                    <p class="mt-1 text-xs text-warning-600 dark:text-orange-400">No approved events are available yet.</p>
                @endif
                @break

            @case(FieldType::WORKPLAN_SELECT)
                @php $workplans = $special['workplans'] ?? []; @endphp
                <select id="{{ $key }}" name="{{ $key }}" class="{{ $inputClass }}"
                    x-data="{ picked: @js((string) $old) }" x-model="picked">
                    <option value="">{{ $placeholder ?: 'Select a workplan' }}</option>
                    @foreach ($workplans as $wp)
                        <option value="{{ $wp['id'] }}">{{ $wp['label'] }}</option>
                    @endforeach
                </select>
                @foreach ($workplans as $wp)
                    <div x-show="picked === '{{ $wp['id'] }}'" x-cloak class="mt-2 rounded-lg border border-gray-200 p-3 text-xs dark:border-gray-700">
                        <p class="mb-1 font-medium text-gray-600 dark:text-gray-300">Approved activities:</p>
                        <ul class="list-disc space-y-0.5 pl-4 text-gray-500 dark:text-gray-400">
                            @foreach ($wp['activities'] as $activity)
                                <li>{{ $activity['title'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @break

            @case(FieldType::WORKPLAN_EVENTS)
                @php
                    $plans = $special['approved_plans'] ?? [];
                    $oldSelected = old($key, array_column($plans, 'id'));
                @endphp
                @if (count($plans))
                    <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-400">All approved events are included by default — untick any to leave out.</p>
                        @foreach ($plans as $plan)
                            <label class="flex cursor-pointer items-start gap-2.5">
                                <input type="checkbox" name="{{ $key }}[]" value="{{ $plan['id'] }}"
                                    @checked(in_array($plan['id'], (array) $oldSelected))
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $plan['title'] }}
                                    <span class="text-xs text-gray-400">— {{ $plan['date'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-warning-600 dark:text-orange-400">No approved events found for your organization's current workplan.</p>
                @endif
                @break

            @case(FieldType::TEXT_LIST)
                @php $oldRows = array_values(array_filter((array) old($key, $prefill[$key] ?? []), fn ($v) => $v !== null)); if (! count($oldRows)) { $oldRows = ['']; } @endphp
                <div x-data="{ rows: {{ Illuminate\Support\Js::from($oldRows) }} }" class="space-y-2">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="'{{ $key }}[]'" x-model="rows[i]" placeholder="{{ $placeholder }}"
                                class="{{ $inputClass }}" />
                            <button type="button" @click="rows.splice(i, 1); if (!rows.length) rows.push('')"
                                class="shrink-0 rounded-lg border border-gray-300 px-2.5 py-2 text-xs text-error-500 dark:border-gray-700">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="rows.push('')" class="text-xs font-medium text-brand-500 hover:text-brand-600">+ Add another</button>
                </div>
                @break

            @case(FieldType::TABLE_INPUT)
                @include('components.form.fields.table-input', ['field' => $field, 'key' => $key, 'opts' => $opts, 'special' => $special ?? []])
                @break

            @case(FieldType::MULTI_IMAGE)
                @php $maxFiles = (int) ($opts['max_files'] ?? 5); @endphp
                <input type="file" id="{{ $key }}" name="{{ $key }}[]" accept="image/jpeg,image/png,image/heic" multiple
                    class="dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border bg-transparent text-sm text-gray-500 file:mr-4 file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-brand-600 dark:border-gray-700 {{ $errors->has($key) ? 'border-error-500' : 'border-gray-300' }}" />
                <p class="mt-1 text-xs text-gray-400">Up to {{ $maxFiles }} photos (JPEG, PNG or HEIC).</p>
                @break

            @case(FieldType::COMPUTED)
                {{-- Derived server-side (FieldCompute overwrites whatever is
                     posted); live-mirrored client-side via computedField so it
                     shows the autofilled value while the form is filled out. --}}
                <div x-data="computedField({
                        formula: @js($opts['formula'] ?? 'sum'),
                        args: @js($opts['args'] ?? []),
                        table: @js($opts['table'] ?? ''),
                        column: @js($opts['column'] ?? ''),
                        fieldKey: @js($key),
                    })" x-effect="value = compute()">
                    <input type="text" id="{{ $key }}" name="{{ $key }}" readonly :value="value"
                        placeholder="Calculated automatically"
                        class="{{ $inputClass }} bg-gray-50 dark:bg-white/[0.02]" />
                </div>
                @break

            @default
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder }}"
                    @isset($opts['max']) maxlength="{{ $opts['max'] }}" @endisset
                    class="{{ $inputClass }}" />
        @endswitch
        @endif

        @error($key)
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
@endif
