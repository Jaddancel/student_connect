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
    <div>
        <label for="{{ $key }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $field->field_label }}
            @if ($required)<span class="text-error-500">*</span>@endif
        </label>

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
                <div x-data="signatureField('{{ $key }}')" class="space-y-2">
                    <canvas x-ref="canvas" width="500" height="160"
                        class="w-full rounded-lg border border-gray-300 bg-white touch-none dark:border-gray-700"></canvas>
                    <input type="hidden" name="{{ $key }}" x-ref="input" value="{{ $old }}" />
                    <button type="button" @click="clear()"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                        Clear signature
                    </button>
                </div>
                @break

            @default
                <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" placeholder="{{ $placeholder }}"
                    @isset($opts['max']) maxlength="{{ $opts['max'] }}" @endisset
                    class="{{ $inputClass }}" />
        @endswitch

        @error($key)
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
@endif
