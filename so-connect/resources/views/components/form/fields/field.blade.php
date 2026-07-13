@php
    use App\Forms\FieldType;

    /** @var \App\Models\Form\FormDescription $field */
    $type = $field->field_type;
    $key = $field->field_key;
    $opts = (array) ($field->field_options ?? []);
    $required = (bool) $field->is_required;
    // Fall back to the universal-field autofill value (from the user's profile)
    // when there's no old() input yet; a resubmit still wins via old().
    $prefill = $prefill ?? [];
    $old = old($key, $prefill[$key] ?? '');
    $placeholder = $field->placeholder_hint ?? '';
    $inputClass = 'dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 '
        . ($errors->has($key) ? 'border-error-500' : 'border-gray-300');
    $pairs = FieldType::optionPairs($opts);
@endphp

@if ($type === FieldType::HEADING)
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
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'Select a date' }}"
                    x-data x-init="window.flatpickr && window.flatpickr($el, { dateFormat: 'Y-m-d' })"
                    class="{{ $inputClass }}" autocomplete="off" />
                @break

            @case(FieldType::NUMBER)
            @case(FieldType::AGE)
                <input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder }}"
                    @isset($opts['min']) min="{{ $opts['min'] }}" @endisset
                    @isset($opts['max']) max="{{ $opts['max'] }}" @endisset
                    step="{{ $opts['step'] ?? ($type === FieldType::AGE ? '1' : 'any') }}"
                    class="{{ $inputClass }}" />
                @break

            @case(FieldType::EMAIL)
                <input type="email" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder ?: 'name@example.com' }}"
                    class="{{ $inputClass }}" />
                @break

            @case(FieldType::IMAGE)
            @case(FieldType::FILE)
                <input type="file" id="{{ $key }}" name="{{ $key }}"
                    @if ($type === FieldType::IMAGE) accept="image/*" @elseif (! empty($opts['accept'])) accept="{{ $opts['accept'] }}" @endif
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 dark:focus:border-brand-800 w-full rounded-lg border bg-transparent text-sm text-gray-500 file:mr-4 file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-brand-600 dark:border-gray-700 {{ $errors->has($key) ? 'border-error-500' : 'border-gray-300' }}" />
                @break

            @case(FieldType::SIGNATURE)
                @php
                    // A saved profile signature arrives as a stored path via prefill;
                    // a re-submitted draw arrives as a data-URL via old().
                    $savedSignatureUrl = (is_string($old) && $old !== '' && ! str_starts_with($old, 'data:'))
                        ? \Illuminate\Support\Facades\Storage::disk(\App\Support\SignatureImage::disk())->url($old)
                        : null;
                @endphp
                <div x-data="signatureField({ verifyUrl: @js(auth()->check() ? route('signature.verify') : null) })" class="space-y-2"
                    @if ($field->universal_key) data-universal-key="{{ $field->universal_key }}" @endif
                    @signature-set="fromDataUrl($event.detail.dataUrl)"
                    @signature-clear="clearFromScan()">
                    @if ($savedSignatureUrl)
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-white/[0.03]">
                            <img src="{{ $savedSignatureUrl }}" alt="Saved signature"
                                 class="h-12 w-auto rounded bg-white object-contain p-1" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Using your saved signature — draw below to replace it for this submission.
                            </p>
                        </div>
                    @endif
                    <canvas x-ref="canvas" width="500" height="160"
                        class="w-full rounded-lg border border-gray-300 bg-white touch-none dark:border-gray-700"></canvas>
                    <input type="hidden" name="{{ $key }}" x-ref="input" value="{{ $old }}" />
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="clear()"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                            Clear signature
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
                        <span x-cloak x-show="verifyState === 'not_recognized'" class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">
                            Not recognized in the system — you can still submit
                        </span>
                        <span x-cloak x-show="verifyState === 'no_signatures'" class="text-xs text-gray-400">
                            No saved signatures to compare against yet
                        </span>
                        <span x-cloak x-show="verifyState === 'unavailable'" class="text-xs text-gray-400">
                            Signature recognition is unavailable right now
                        </span>
                    </div>
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
