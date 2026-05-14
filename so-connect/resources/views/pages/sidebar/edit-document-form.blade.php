@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">

        {{-- Form metadata --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Form Settings</h3>

            @if (session('success'))
                <div class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('forms.update', ['formId' => $form->id]) }}" method="post" class="mt-5 space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Form Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name', $form->name) }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Organization
                        </label>
                        <select id="organization_id" name="organization_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="" @selected(old('organization_id', $form->organization_id) === null)>All organizations</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->organization_id }}"
                                    @selected((int) old('organization_id', $form->organization_id) === (int) $organization->organization_id)>
                                    {{ $organization->organization_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Role Level <span class="text-error-500">*</span>
                        </label>
                        @php $currentRoleLevels = (array) old('sidebar_group', $form->sidebar_group ?? ['president']); @endphp
                        <div class="flex flex-wrap gap-4 rounded-lg border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">
                            @foreach ($roleLevels as $roleLevel => $roleLabel)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="sidebar_group[]" value="{{ $roleLevel }}"
                                        @checked(in_array($roleLevel, $currentRoleLevels))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                                    {{ $roleLabel }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select all role levels that should see this form.</p>
                    </div>

                    <div>
                        <label for="request_type_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Request Type <span class="text-error-500">*</span>
                        </label>
                        <select id="request_type_id" name="request_type_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select request type</option>
                            @foreach ($requestTypesByCategory as $category => $requestTypes)
                                <optgroup label="{{ \App\Models\RequestType::categoryLabelForContext($category, 'requests') }}">
                                    @foreach ($requestTypes as $requestType)
                                        <option value="{{ $requestType->request_type_id }}"
                                            @selected((int) old('request_type_id', $form->request_type_id) === (int) $requestType->request_type_id)>
                                            {{ $requestType->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="description_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Description
                        </label>
                        <textarea id="description_text" name="description_text" rows="2"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('description_text', $form->description_text) }}</textarea>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                            @checked(old('is_active', $form->is_active))
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">
                            Form is active (visible to members)
                        </label>
                    </div>
                </div>

                {{-- Field editor --}}
                <div>
                    <h4 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Fields</h4>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        You can change labels, input types, and options. Placeholder bindings cannot be changed here — upload a new template to restructure fields.
                    </p>

                    <div class="space-y-3">
                        @foreach ($form->fields as $field)
                            @php
                                $isMultiline = str_contains((string) $field->placeholder_hint, '#}}');
                                $currentType = old('fields.' . $field->field_key . '.field_type', $field->field_type);
                                $currentLabel = old('fields.' . $field->field_key . '.field_label', $field->field_label);
                                $currentRequired = (bool) old('fields.' . $field->field_key . '.is_required', $field->is_required);

                                // Resolve current static options for select
                                $existingStaticOptions = '';
                                if ($field->field_type === 'select' && is_array($field->field_options)) {
                                    $existingStaticOptions = implode("\n", $field->field_options);
                                }
                                $currentStaticOptions = old('fields.' . $field->field_key . '.static_options', $existingStaticOptions);

                                // Resolve current DB source for dynamicSelect
                                $existingDbSource = 'organizations';
                                if ($field->field_type === 'dynamicSelect' && is_array($field->field_options)) {
                                    $existingDbSource = $field->field_options['source'] ?? 'organizations';
                                }
                                $currentDbSource = old('fields.' . $field->field_key . '.db_source', $existingDbSource);
                            @endphp

                            <div x-data="{
                                    fieldType: '{{ $currentType }}',
                                    get isSelect() { return this.fieldType === 'select'; },
                                    get isDynamicSelect() { return this.fieldType === 'dynamicSelect'; }
                                }"
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30">

                                <div class="mb-3 flex flex-wrap items-start gap-x-4 gap-y-1">
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $field->placeholder_hint }}
                                    </span>
                                    @if ($isMultiline)
                                        <span class="inline-flex rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                                            multiline
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $field->mappings->count() }} placeholder slot(s)
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    {{-- Label --}}
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Label</label>
                                        <input type="text" name="fields[{{ $field->field_key }}][field_label]"
                                            value="{{ $currentLabel }}"
                                            class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </div>

                                    {{-- Type --}}
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Input Type</label>
                                        <select name="fields[{{ $field->field_key }}][field_type]"
                                            x-model="fieldType"
                                            class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            @if ($isMultiline)
                                                <option value="multipleInputs" @selected($currentType === 'multipleInputs')>Row-by-row (text)</option>
                                                <option value="dynamicSelect" @selected($currentType === 'dynamicSelect')>Select from database</option>
                                            @else
                                                <option value="text" @selected($currentType === 'text')>Text</option>
                                                <option value="textarea" @selected($currentType === 'textarea')>Textarea</option>
                                                <option value="email" @selected($currentType === 'email')>Email</option>
                                                <option value="date" @selected($currentType === 'date')>Date</option>
                                                <option value="select" @selected($currentType === 'select')>Select (static options)</option>
                                            @endif
                                        </select>
                                    </div>

                                    {{-- Required --}}
                                    <div class="flex items-end pb-1">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="fields[{{ $field->field_key }}][is_required]" value="1"
                                                @checked($currentRequired)
                                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                                            Required
                                        </label>
                                    </div>
                                </div>

                                {{-- Static options (shown when type = select) --}}
                                <div x-show="isSelect" x-cloak class="mt-3">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Options <span class="font-normal text-gray-400">(one per line)</span>
                                    </label>
                                    <textarea name="fields[{{ $field->field_key }}][static_options]" rows="3"
                                        placeholder="Option A&#10;Option B&#10;Option C"
                                        class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ $currentStaticOptions }}</textarea>
                                </div>

                                {{-- DB source (shown when type = dynamicSelect) --}}
                                <div x-show="isDynamicSelect" x-cloak class="mt-3">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Pull options from
                                    </label>
                                    <select name="fields[{{ $field->field_key }}][db_source]"
                                        class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                        <option value="organizations" @selected($currentDbSource === 'organizations')>Organizations (scoped to role)</option>
                                        <option value="users" @selected($currentDbSource === 'users')>Users (scoped to role)</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        Presidents see their organizations/members. Superadmins see all.
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                    <a href="{{ route('forms.manage') }}"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        ← Back to Forms
                    </a>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- Template info (readonly) --}}
        @if ($form->templates->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h4 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Attached Templates</h4>
                <div class="space-y-2">
                    @foreach ($form->templates->sortByDesc('version') as $template)
                        <div class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3 dark:border-gray-800">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $template->template_name }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Version {{ $template->version }} &middot; {{ $template->created_at?->format('M d, Y') }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $template->is_active ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-gray-100 text-gray-500' }}">
                                {{ $template->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
