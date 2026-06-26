@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Form" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="4" :total="5" :labels="[1 => 'Upload', 2 => 'Details', 3 => 'AI Review', 4 => 'Revise', 5 => 'Confirm']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Step 4 — Revise Fields</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Add, edit, or remove form fields. Field keys are used as placeholders in DOCX templates.
                    </p>
                </div>
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

        <form action="{{ route('admin.form-wizard.revise.save') }}" method="POST">
            @csrf

            <div x-data="{
                fields: {{ json_encode(
                    $form->fields->isNotEmpty()
                        ? $form->fields->map(fn($f) => [
                            'label' => $f->field_label,
                            'field_key' => $f->field_key,
                            'field_type' => $f->field_type,
                            'is_required' => $f->is_required,
                            'field_order' => $f->field_order,
                            'field_options' => $f->field_options ?? [],
                          ])->values()->all()
                        : collect($aiFields)->map(fn($f) => [
                            'label' => $f['label'] ?? '',
                            'field_key' => $f['field_key'] ?? '',
                            'field_type' => $f['field_type'] ?? 'text',
                            'is_required' => (bool) ($f['is_required'] ?? false),
                            'field_order' => $f['field_order'] ?? 0,
                            'field_options' => $f['field_options'] ?? [],
                          ])->values()->all()
                ) }},
                slugify(value) {
                    return (value || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                },
                addField() {
                    this.fields.push({
                        label: '',
                        field_key: '',
                        field_type: 'text',
                        is_required: false,
                        field_order: this.fields.length + 1,
                        field_options: []
                    });
                },
                removeField(idx) {
                    this.fields.splice(idx, 1);
                },
                autoKey(idx) {
                    this.fields[idx].field_key = this.slugify(this.fields[idx].label);
                },
                onTypeChange(idx) {
                    if (this.fields[idx].field_type === 'repeating' && this.fields[idx].field_options.length === 0) {
                        this.addSubField(idx);
                    }
                },
                addSubField(idx) {
                    this.fields[idx].field_options.push({ label: '', field_key: '', field_type: 'text' });
                },
                removeSubField(idx, sidx) {
                    this.fields[idx].field_options.splice(sidx, 1);
                },
                autoSubKey(idx, sidx) {
                    this.fields[idx].field_options[sidx].field_key = this.slugify(this.fields[idx].field_options[sidx].label);
                }
            }" class="space-y-6">

                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Fields</h3>
                        <button type="button" @click="addField()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-400 dark:hover:bg-brand-500/10">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Field
                        </button>
                    </div>

                    <template x-if="fields.length === 0">
                        <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                            No fields yet. Click "Add Field" to start.
                        </div>
                    </template>

                    <template x-if="fields.length > 0">
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="(field, idx) in fields" :key="idx">
                                <div class="px-5 py-4 space-y-3">
                                <div class="grid grid-cols-12 gap-3 items-start">
                                    {{-- Order --}}
                                    <div class="col-span-1 pt-2 text-xs text-gray-400 dark:text-gray-500" x-text="idx + 1"></div>

                                    {{-- Label --}}
                                    <div class="col-span-4">
                                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Label *</label>
                                        <input type="text" :name="`fields[${idx}][label]`" x-model="field.label"
                                               @blur="autoKey(idx)"
                                               placeholder="e.g. Student Name"
                                               class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90" />
                                    </div>

                                    {{-- Field Key --}}
                                    <div class="col-span-3">
                                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Field Key *</label>
                                        <input type="text" :name="`fields[${idx}][field_key]`" x-model="field.field_key"
                                               placeholder="student_name"
                                               class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 font-mono text-xs text-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90" />
                                    </div>

                                    {{-- Type --}}
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Type</label>
                                        <select :name="`fields[${idx}][field_type]`" x-model="field.field_type"
                                                @change="onTypeChange(idx)"
                                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-700 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90">
                                            <option value="text">text</option>
                                            <option value="textarea">textarea</option>
                                            <option value="date">date</option>
                                            <option value="number">number</option>
                                            <option value="email">email</option>
                                            <option value="checkbox">checkbox</option>
                                            <option value="signature">signature</option>
                                            <option value="repeating">repeating</option>
                                        </select>
                                    </div>

                                    {{-- Required + remove --}}
                                    <div class="col-span-2 flex flex-col gap-2 pt-5">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" :name="`fields[${idx}][is_required]`" x-model="field.is_required"
                                                   class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-400" />
                                            <span class="text-xs text-gray-600 dark:text-gray-400">Required</span>
                                        </label>
                                        <input type="hidden" :name="`fields[${idx}][field_order]`" :value="idx + 1" />
                                        <button type="button" @click="removeField(idx)"
                                                class="self-start text-xs font-medium text-error-500 hover:text-error-700 dark:text-error-400">
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                {{-- Signature hint --}}
                                <template x-if="field.field_type === 'signature'">
                                    <p class="ml-[8.333%] rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                                        Applicants will draw or upload a signature for this field. Captured signatures are checked against past submissions for verification.
                                    </p>
                                </template>

                                {{-- Repeating sub-fields --}}
                                <template x-if="field.field_type === 'repeating'">
                                    <div class="ml-[8.333%] rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                Item fields <span class="text-gray-400 dark:text-gray-500">— repeated for each entry the applicant adds</span>
                                            </p>
                                            <button type="button" @click="addSubField(idx)"
                                                    class="inline-flex items-center gap-1 rounded-md border border-brand-300 px-2 py-1 text-[11px] font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-400 dark:hover:bg-brand-500/10">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Add Item Field
                                            </button>
                                        </div>

                                        <template x-if="field.field_options.length === 0">
                                            <p class="py-2 text-center text-[11px] text-gray-400 dark:text-gray-500">No item fields yet.</p>
                                        </template>

                                        <div class="space-y-2">
                                            <template x-for="(sub, sidx) in field.field_options" :key="sidx">
                                                <div class="grid grid-cols-12 gap-2 items-center">
                                                    <input type="text" :name="`fields[${idx}][field_options][${sidx}][label]`" x-model="sub.label"
                                                           @blur="autoSubKey(idx, sidx)" placeholder="Item label (e.g. Activity)"
                                                           class="col-span-5 h-8 w-full rounded-md border border-gray-300 bg-transparent px-2 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90" />
                                                    <input type="text" :name="`fields[${idx}][field_options][${sidx}][field_key]`" x-model="sub.field_key"
                                                           placeholder="activity"
                                                           class="col-span-3 h-8 w-full rounded-md border border-gray-300 bg-transparent px-2 font-mono text-[11px] text-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90" />
                                                    <select :name="`fields[${idx}][field_options][${sidx}][field_type]`" x-model="sub.field_type"
                                                            class="col-span-3 h-8 w-full rounded-md border border-gray-300 bg-transparent px-2 text-xs text-gray-700 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90">
                                                        <option value="text">text</option>
                                                        <option value="textarea">textarea</option>
                                                        <option value="date">date</option>
                                                        <option value="number">number</option>
                                                        <option value="email">email</option>
                                                        <option value="checkbox">checkbox</option>
                                                    </select>
                                                    <button type="button" @click="removeSubField(idx, sidx)"
                                                            class="col-span-1 text-[11px] font-medium text-error-500 hover:text-error-700 dark:text-error-400">✕</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.form-wizard.ai-review') }}"
                           class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            ← Back
                        </a>
                        <button type="submit"
                                class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Save &amp; Continue →
                        </button>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
