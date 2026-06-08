@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Detected Fields" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Review Detected Fields</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Fields auto-detected from <span class="font-medium text-gray-700 dark:text-gray-200">{{ $form->name }}</span>.
                        Edit, remove, or add rows, then confirm to save them to the form.
                    </p>
                </div>
                <a href="{{ route('admin.form-derivation.upload') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                    Back
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.form-derivation.confirm', $form) }}" method="POST"
              x-data="{
                  types: {{ Js::from($fieldTypes) }},
                  rows: {{ Js::from($detectedFields) }},
                  addRow() {
                      this.rows.push({ field_label: '', field_key: '', field_type: 'text', is_required: false, field_order: this.rows.length + 1 });
                  },
                  removeRow(i) { this.rows.splice(i, 1); },
                  keyify(label) {
                      return (label || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
                  }
              }">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        Detected Fields (<span x-text="rows.length"></span>)
                    </h3>
                    <button type="button" @click="addRow()"
                            class="flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add field
                    </button>
                </div>

                <template x-if="rows.length === 0">
                    <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                        No fields detected. Click “Add field” to create them manually.
                    </p>
                </template>

                <div class="overflow-x-auto" x-show="rows.length > 0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                <th class="px-2 py-2">Label</th>
                                <th class="px-2 py-2">Key</th>
                                <th class="px-2 py-2 w-32">Type</th>
                                <th class="px-2 py-2 w-20 text-center">Required</th>
                                <th class="px-2 py-2 w-20">Order</th>
                                <th class="px-2 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in rows" :key="i">
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-2">
                                        <input type="text" :name="`fields[${i}][field_label]`"
                                               x-model="row.field_label"
                                               @input="if (!row._keyTouched) row.field_key = keyify(row.field_label)"
                                               class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="text" :name="`fields[${i}][field_key]`"
                                               x-model="row.field_key" @input="row._keyTouched = true"
                                               class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 font-mono text-xs text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <select :name="`fields[${i}][field_type]`" x-model="row.field_type"
                                                class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            <template x-for="t in types" :key="t">
                                                <option :value="t" x-text="t" :selected="t === row.field_type"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <input type="hidden" :name="`fields[${i}][is_required]`" :value="row.is_required ? 1 : 0" />
                                        <input type="checkbox" x-model="row.is_required"
                                               class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" min="0" :name="`fields[${i}][field_order]`"
                                               x-model="row.field_order"
                                               class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeRow(i)"
                                                class="rounded-lg border border-gray-300 p-2 text-gray-400 transition hover:border-error-400 hover:text-error-500 dark:border-gray-700 dark:text-gray-500">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Actions --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex items-center justify-end gap-3">
                    {{-- Discard submits a separate form (defined below) to avoid nesting forms. --}}
                    <button type="submit" form="discard-draft-form"
                            onclick="return confirm('Discard this draft form and its detected fields?');"
                            class="rounded-lg border border-error-300 px-4 py-2.5 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-700 dark:text-error-400 dark:hover:bg-error-900/20">
                        Discard draft
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Confirm &amp; Save Fields
                    </button>
                </div>
            </div>
        </form>

        {{-- Standalone discard form (kept out of the main form to keep HTML valid) --}}
        <form id="discard-draft-form" action="{{ route('admin.form-derivation.destroy', $form) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection
