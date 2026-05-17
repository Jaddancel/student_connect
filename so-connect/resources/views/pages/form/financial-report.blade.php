@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Financial Report" />

    <div class="space-y-6">

        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if(session('status'))
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">FINANCIAL REPORT</p>
        </div>

        <form action="{{ route('financial-report.store') }}" method="POST" enctype="multipart/form-data"
            class="space-y-6"
            x-data="{
                orgName: '{{ addslashes($organizations->first()?->organization_name ?? '') }}',
                fundRows: [{ fundSource: '', fundAmount: '' }],
                expenseRows: [{ activityTitle: '', activityDate: '', item: '', amountPerUnit: '', quantity: '' }],

                addFundRow() { this.fundRows.push({ fundSource: '', fundAmount: '' }); },
                removeFundRow(i) { if (this.fundRows.length > 1) this.fundRows.splice(i, 1); },

                addExpenseRow() { this.expenseRows.push({ activityTitle: '', activityDate: '', item: '', amountPerUnit: '', quantity: '' }); },
                removeExpenseRow(i) { if (this.expenseRows.length > 1) this.expenseRows.splice(i, 1); },

                rowTotal(row) {
                    return (parseFloat(row.amountPerUnit) || 0) * (parseFloat(row.quantity) || 0);
                },

                get totalFunds() {
                    return this.fundRows.reduce((s, r) => s + (parseFloat(r.fundAmount) || 0), 0);
                },

                get totalExpenses() {
                    return this.expenseRows.reduce((s, r) => s + this.rowTotal(r), 0);
                },

                get cashOnHand() {
                    return this.totalFunds - this.totalExpenses;
                },

                fmt(v) {
                    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
                },

                onOrgChange(el) {
                    const opt = el.options[el.selectedIndex];
                    this.orgName = opt ? opt.dataset.name : '';
                    el.form.querySelector('[name=organization]').value = this.orgName;
                },
            }">
            @csrf

            @if($errors->any())
                <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- SECTION 1 · ORGANIZATION & SCHOOL YEAR --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if($organizations->count() > 1)
                            <select name="organization_id"
                                @change="onOrgChange($el)"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                @foreach($organizations as $org)
                                    <option value="{{ $org->organization_id }}"
                                        data-name="{{ $org->organization_name }}"
                                        @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="organization" x-bind:value="orgName" value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}" />
                        @else
                            <input type="text" name="organization" value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id" value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>

                    <div>
                        <label for="schoolYear" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="schoolYear" name="schoolYear" value="{{ old('schoolYear') }}"
                            placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- SECTION 2 · SOURCE OF FUNDS --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Source of Funds</h3>
                    <button type="button" @click="addFundRow()"
                        class="flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/10">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Row
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 w-full">Source of Funds</th>
                                <th class="pb-2 pl-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Amount (₱)</th>
                                <th class="pb-2 pl-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in fundRows" :key="i">
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3">
                                        <input type="text" name="fundSource[]"
                                            x-model="row.fundSource"
                                            placeholder="e.g. Membership Fees"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2">
                                        <input type="number" name="fundAmount[]"
                                            x-model="row.fundAmount"
                                            placeholder="0.00" min="0" step="0.01"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-32 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-2">
                                        <button type="button" @click="removeFundRow(i)"
                                            x-show="fundRows.length > 1"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-900/20">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                                <td class="pt-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Total Fund</td>
                                <td class="pt-3 pl-3 text-right text-sm font-bold text-gray-900 dark:text-white" x-text="'₱ ' + fmt(totalFunds)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <input type="hidden" name="totalFunds" x-bind:value="totalFunds" />
            </div>

            {{-- SECTION 3 · SUMMARY OF EXPENSES --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Summary of Expenses</h3>
                    <button type="button" @click="addExpenseRow()"
                        class="flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/10">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Row
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Activity</th>
                                <th class="pb-2 pl-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Date</th>
                                <th class="pb-2 pl-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</th>
                                <th class="pb-2 pl-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 whitespace-nowrap">Unit Price (₱)</th>
                                <th class="pb-2 pl-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Qty</th>
                                <th class="pb-2 pl-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total (₱)</th>
                                <th class="pb-2 pl-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in expenseRows" :key="i">
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-2">
                                        <input type="text" name="activityTitle[]"
                                            x-model="row.activityTitle"
                                            placeholder="Activity"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-36 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2">
                                        <input type="date" name="activityDate[]"
                                            x-model="row.activityDate"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-36 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2">
                                        <input type="text" name="item[]"
                                            x-model="row.item"
                                            placeholder="Item"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-32 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2">
                                        <input type="number" name="amountPerUnit[]"
                                            x-model="row.amountPerUnit"
                                            placeholder="0.00" min="0" step="0.01"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-28 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2">
                                        <input type="number" name="quantity[]"
                                            x-model="row.quantity"
                                            placeholder="0" min="0" step="1"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pl-3 pr-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                        x-text="fmt(rowTotal(row))"></td>
                                    <td class="py-2 pl-2">
                                        <button type="button" @click="removeExpenseRow(i)"
                                            x-show="expenseRows.length > 1"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-900/20">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="totalExpenses" x-bind:value="totalExpenses" />
                <input type="hidden" name="cashOnHand" x-bind:value="cashOnHand" />

                {{-- Financial Summary --}}
                <div class="mt-4 ml-auto w-full max-w-xs space-y-1.5 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>Total Fund</span>
                        <span class="font-medium text-gray-800 dark:text-white/90" x-text="'₱ ' + fmt(totalFunds)"></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>Total Expenses</span>
                        <span class="font-medium text-gray-800 dark:text-white/90" x-text="'₱ ' + fmt(totalExpenses)"></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-1.5 text-sm font-semibold dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-300">Cash on Hand</span>
                        <span x-bind:class="cashOnHand < 0 ? 'text-error-600' : 'text-success-600'" x-text="'₱ ' + fmt(cashOnHand)"></span>
                    </div>
                </div>
            </div>

            {{-- SECTION 4 · SIGNATURES --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-6 text-base font-semibold text-gray-800 dark:text-white/90">Signatures</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                    {{-- Treasurer --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Prepared by (Treasurer) <span class="text-error-500">*</span></p>
                        <div>
                            <label for="nameOfTreasurer" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                            <input type="text" id="nameOfTreasurer" name="nameOfTreasurer" value="{{ old('nameOfTreasurer') }}"
                                placeholder="Full name of Treasurer"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signatureTreasurer"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z"/></svg>
                                </template>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400" x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="signatureTreasurer" name="signatureTreasurer" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name of the Treasurer</p>
                        </div>
                    </div>

                    {{-- Auditor --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Audited by <span class="text-error-500">*</span></p>
                        <div>
                            <label for="nameOfTheAuditor" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                            <input type="text" id="nameOfTheAuditor" name="nameOfTheAuditor" value="{{ old('nameOfTheAuditor') }}"
                                placeholder="Full name of Auditor"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signatureAuditor"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z"/></svg>
                                </template>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400" x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="signatureAuditor" name="signatureAuditor" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name of the Auditor</p>
                        </div>
                    </div>

                    {{-- President --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Noted by (President) <span class="text-error-500">*</span></p>
                        <div>
                            <label for="nameOfThePresident" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                            <input type="text" id="nameOfThePresident" name="nameOfThePresident" value="{{ old('nameOfThePresident') }}"
                                placeholder="Full name of President"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signaturePresident"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z"/></svg>
                                </template>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400" x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="signaturePresident" name="signaturePresident" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name of the President</p>
                        </div>
                    </div>

                    {{-- Adviser --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Approved by (Adviser) <span class="text-error-500">*</span></p>
                        <div>
                            <label for="nameOfTheAdviser" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                            <input type="text" id="nameOfTheAdviser" name="nameOfTheAdviser" value="{{ old('nameOfTheAdviser') }}"
                                placeholder="Full name of Adviser"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signatureAdviser"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z"/></svg>
                                </template>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400" x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="signatureAdviser" name="signatureAdviser" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name of the Adviser</p>
                        </div>
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit &amp; Generate PDF
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
