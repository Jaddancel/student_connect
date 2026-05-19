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

        {{-- University header --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">FINANCIAL REPORT</p>
        </div>

        @php
            $fundSources = old('fundSource', ['']);
            $fundAmounts = old('amount', [0]);
            $fundRowsData = collect($fundSources)->map(function ($s, $i) use ($fundAmounts) {
                return ['source' => (string) ($s ?? ''), 'amount' => (float) ($fundAmounts[$i] ?? 0)];
            })->values()->all();

            $activityTitles = old('activityTitle', ['']);
            $activityDates  = old('activityDate', []);
            $items          = old('item', []);
            $amountsPerUnit = old('amountPerUnit', []);
            $quantities     = old('quantity', []);
            $expenseRowsData = collect($activityTitles)->map(function ($t, $i) use ($activityDates, $items, $amountsPerUnit, $quantities) {
                return [
                    'activityTitle' => (string) ($t ?? ''),
                    'activityDate'  => (string) ($activityDates[$i] ?? ''),
                    'item'          => (string) ($items[$i] ?? ''),
                    'amountPerUnit' => (float) ($amountsPerUnit[$i] ?? 0),
                    'quantity'      => (float) ($quantities[$i] ?? 0),
                ];
            })->values()->all();
        @endphp

        <form action="{{ route('financial-report.store') }}" method="POST" enctype="multipart/form-data"
            class="space-y-6"
            x-data="{
                orgName: '{{ addslashes(old('organization', $organizations->first()?->organization_name ?? '')) }}',
                selectedOrgId: {{ (int) ($organizations->first()?->organization_id ?? 0) }},
                orgEvents: {{ Js::from($orgEvents) }},
                get currentOrgEvents() { return this.orgEvents[this.selectedOrgId] || []; },
                onOrgChange(el) {
                    const opt = el.options[el.selectedIndex];
                    this.orgName = opt ? opt.dataset.name : '';
                    this.selectedOrgId = opt ? parseInt(opt.value) : 0;
                    el.form.querySelector('[name=organization]').value = this.orgName;
                },

                fundRows: {{ Js::from($fundRowsData) }},
                addFundRow() { this.fundRows.push({ source: '', amount: 0 }); },
                removeFundRow(i) { if (this.fundRows.length > 1) this.fundRows.splice(i, 1); },
                get totalFunds() { return this.fundRows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0); },

                expenseRows: {{ Js::from($expenseRowsData) }},
                addExpenseRow() { this.expenseRows.push({ activityTitle: '', activityDate: '', item: '', amountPerUnit: 0, quantity: 0 }); },
                removeExpenseRow(i) { if (this.expenseRows.length > 1) this.expenseRows.splice(i, 1); },
                rowTotal(row) { return (parseFloat(row.amountPerUnit) || 0) * (parseFloat(row.quantity) || 0); },
                get totalExpenses() { return this.expenseRows.reduce((s, r) => s + this.rowTotal(r), 0); },
                get cashOnHand() { return this.totalFunds - this.totalExpenses; },

                fmt(n) { return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n); },
            }">
            @csrf

            @if($errors->any())
                <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- I. ORGANIZATION DETAILS --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    I. Organization Details
                </h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if($organizations->count() > 1)
                            <select name="organization_id" @change="onOrgChange($el)"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                @foreach($organizations as $org)
                                    <option value="{{ $org->organization_id }}"
                                        data-name="{{ $org->organization_name }}"
                                        @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="organization" x-bind:value="orgName"
                                value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}" />
                        @else
                            <input type="text" name="organization"
                                value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id" value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>
                    <div>
                        <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="date" name="date"
                            value="{{ old('date', $currentSchoolYear ?? '') }}" placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('date') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('date')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- II. SOURCE OF FUNDS --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                        II. Source of Funds
                    </h3>
                    <button type="button" @click="addFundRow()"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-palette-lime bg-palette-lime-pale px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-palette-lime dark:border-palette-lime/40 dark:bg-palette-lime/10 dark:text-white/80 dark:hover:bg-palette-lime/20">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Row
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Source of Funds
                                </th>
                                <th class="w-44 px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Amount (₱)
                                </th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <template x-for="(row, i) in fundRows" :key="i">
                                <tr>
                                    <td class="px-4 py-2">
                                        <input type="text"
                                            :name="'fundSource[' + i + ']'"
                                            x-model="row.source"
                                            placeholder="e.g. Membership Fees, Fundraising"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number"
                                            :name="'amount[' + i + ']'"
                                            x-model.number="row.amount"
                                            min="0" step="0.01" placeholder="0.00"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeFundRow(i)" x-show="fundRows.length > 1"
                                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/10 dark:hover:text-error-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Total Funds</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white"
                                    x-text="'₱ ' + fmt(totalFunds)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <input type="hidden" name="totalFunds" x-bind:value="totalFunds" />
            </div>

            {{-- III. STATEMENT OF EXPENSES --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                        III. Statement of Expenses
                    </h3>
                    <button type="button" @click="addExpenseRow()"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-palette-lime bg-palette-lime-pale px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-palette-lime dark:border-palette-lime/40 dark:bg-palette-lime/10 dark:text-white/80 dark:hover:bg-palette-lime/20">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Row
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 min-w-[150px]">Activity</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 w-36">Date</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 min-w-[130px]">Item</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 w-32">Price / Unit</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 w-24">Qty</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 w-32">Total</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <template x-for="(row, i) in expenseRows" :key="i">
                                <tr>
                                    <td class="px-3 py-2">
                                        <select
                                            :name="'activityTitle[' + i + ']'"
                                            x-model="row.activityTitle"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            <option value="">— Select activity —</option>
                                            <template x-for="ev in currentOrgEvents" :key="ev.name + ev.date">
                                                <option :value="ev.name" x-text="ev.name + (ev.date ? ' (' + ev.date + ')' : '')"></option>
                                            </template>
                                            <template x-if="currentOrgEvents.length === 0">
                                                <option value="" disabled>No calendar events found</option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="date"
                                            :name="'activityDate[' + i + ']'"
                                            x-model="row.activityDate"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text"
                                            :name="'item[' + i + ']'"
                                            x-model="row.item"
                                            placeholder="Description"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number"
                                            :name="'amountPerUnit[' + i + ']'"
                                            x-model.number="row.amountPerUnit"
                                            min="0" step="0.01" placeholder="0.00"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number"
                                            :name="'quantity[' + i + ']'"
                                            x-model.number="row.quantity"
                                            min="0" step="1" placeholder="0"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-right text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                        x-text="'₱ ' + fmt(rowTotal(row))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeExpenseRow(i)" x-show="expenseRows.length > 1"
                                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/10 dark:hover:text-error-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                                <td colspan="5" class="px-3 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    Total Expenses
                                </td>
                                <td class="px-3 py-3 text-right text-sm font-bold text-gray-900 dark:text-white"
                                    x-text="'₱ ' + fmt(totalExpenses)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <input type="hidden" name="totalExpenses" x-bind:value="totalExpenses" />
            </div>

            {{-- IV. FINANCIAL SUMMARY --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    IV. Financial Summary
                </h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Funds</p>
                        <p class="mt-1.5 text-2xl font-bold text-gray-900 dark:text-white" x-text="'₱ ' + fmt(totalFunds)"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Expenses</p>
                        <p class="mt-1.5 text-2xl font-bold text-error-600 dark:text-error-400" x-text="'₱ ' + fmt(totalExpenses)"></p>
                    </div>
                    <div class="rounded-xl border px-5 py-4 transition-colors"
                        :class="cashOnHand >= 0
                            ? 'border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-500/10'
                            : 'border-error-200 bg-error-50 dark:border-error-500/30 dark:bg-error-500/10'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash on Hand</p>
                        <p class="mt-1.5 text-2xl font-bold"
                            :class="cashOnHand >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'"
                            x-text="'₱ ' + fmt(cashOnHand)"></p>
                    </div>
                </div>
                <input type="hidden" name="cashOnHand" x-bind:value="cashOnHand" />
            </div>

            {{-- V. SIGNATORIES --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-5 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    V. Signatories
                </h3>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

                    {{-- Treasurer --}}
                    <div x-data="{ preview: null }" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Treasurer
                        </p>
                        <div class="space-y-3">
                            <div>
                                <label for="name_of_treasurer" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Full Name <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="name_of_treasurer" name="name_of_treasurer"
                                    value="{{ old('name_of_treasurer') }}" placeholder="Treasurer's full name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border {{ $errors->has('name_of_treasurer') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                @error('name_of_treasurer')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Signature <span class="text-error-500">*</span>
                                </label>
                                <label for="signature1"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature1') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-3 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                    <template x-if="preview">
                                        <img :src="preview" class="mb-2 max-h-16 rounded object-contain" alt="Signature" />
                                    </template>
                                    <template x-if="!preview">
                                        <svg class="mb-1.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </template>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        x-text="preview ? 'Change' : 'Upload signature'"></span>
                                    <span class="text-xs text-gray-400">JPG or PNG · max 2 MB</span>
                                    <input id="signature1" name="signature1" type="file"
                                        accept="image/jpeg,image/png" class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('signature1')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Auditor --}}
                    <div x-data="{ preview: null }" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Auditor
                        </p>
                        <div class="space-y-3">
                            <div>
                                <label for="name_of_the_auditor" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Full Name <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="name_of_the_auditor" name="name_of_the_auditor"
                                    value="{{ old('name_of_the_auditor') }}" placeholder="Auditor's full name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border {{ $errors->has('name_of_the_auditor') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                @error('name_of_the_auditor')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Signature <span class="text-error-500">*</span>
                                </label>
                                <label for="signature2"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature2') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-3 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                    <template x-if="preview">
                                        <img :src="preview" class="mb-2 max-h-16 rounded object-contain" alt="Signature" />
                                    </template>
                                    <template x-if="!preview">
                                        <svg class="mb-1.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </template>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        x-text="preview ? 'Change' : 'Upload signature'"></span>
                                    <span class="text-xs text-gray-400">JPG or PNG · max 2 MB</span>
                                    <input id="signature2" name="signature2" type="file"
                                        accept="image/jpeg,image/png" class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('signature2')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- President --}}
                    <div x-data="{ preview: null }" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            President
                        </p>
                        <div class="space-y-3">
                            <div>
                                <label for="name_of_the_president" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Full Name <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="name_of_the_president" name="name_of_the_president"
                                    value="{{ old('name_of_the_president') }}" placeholder="President's full name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border {{ $errors->has('name_of_the_president') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                @error('name_of_the_president')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Signature <span class="text-error-500">*</span>
                                </label>
                                <label for="signature3"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature3') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-3 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                    <template x-if="preview">
                                        <img :src="preview" class="mb-2 max-h-16 rounded object-contain" alt="Signature" />
                                    </template>
                                    <template x-if="!preview">
                                        <svg class="mb-1.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </template>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        x-text="preview ? 'Change' : 'Upload signature'"></span>
                                    <span class="text-xs text-gray-400">JPG or PNG · max 2 MB</span>
                                    <input id="signature3" name="signature3" type="file"
                                        accept="image/jpeg,image/png" class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('signature3')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Adviser --}}
                <div class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                    <div x-data="{ preview: null }" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="name_of_the_adviser" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Faculty Adviser <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="name_of_the_adviser" name="name_of_the_adviser"
                                value="{{ old('name_of_the_adviser') }}" placeholder="Adviser's full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('name_of_the_adviser') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('name_of_the_adviser')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser Signature <span class="text-error-500">*</span>
                            </label>
                            <label for="signature4"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature4') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-3 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <template x-if="preview">
                                    <img :src="preview" class="mb-2 max-h-16 rounded object-contain" alt="Signature" />
                                </template>
                                <template x-if="!preview">
                                    <svg class="mb-1.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                </template>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                    x-text="preview ? 'Change' : 'Upload signature'"></span>
                                <span class="text-xs text-gray-400">JPG or PNG · max 2 MB</span>
                                <input id="signature4" name="signature4" type="file"
                                    accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            @error('signature4')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex justify-end">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-palette-lime px-8 py-3 text-sm font-semibold text-gray-800 shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-palette-lime/60">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Submit Financial Report
                </button>
            </div>

        </form>
    </div>
@endsection
