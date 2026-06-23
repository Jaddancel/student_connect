@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="3" :total="3" :labels="[1 => 'Workplan', 2 => 'Signature', 3 => 'Review & Submit']" />

        {{-- ── FORM HEADER ──────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <div class="text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Step 3 — Review &amp; Submit
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Review the pre-filled information below, make any corrections, then submit for approval.
                </p>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @php
            $alphaPsByOrg = $presidentsByOrg;
            $alphaPresName = old('name_of_president', $presidentName);
        @endphp

        <form action="{{ route('accreditation.wizard.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Hidden: pre-stored signature path from step 2 --}}
            <input type="hidden" name="sig_path" value="{{ $sessionSigPath }}" />

            {{-- ── SECTION 1 · PLEASE CHECK ──────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Please Check</h3>
                <div class="space-y-3">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="radio" name="recognition_type" value="c1"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                            {{ old('recognition_type') === 'c1' ? 'checked' : '' }} />
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recognition</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="radio" name="recognition_type" value="c2"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                            {{ old('recognition_type') === 'c2' ? 'checked' : '' }} />
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Renewal</span>
                    </label>
                </div>
            </div>

            {{-- ── SECTION 2 · BASIC INFORMATION ─────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    freshman: {{ old('freshman', 0) }},
                    sophomore: {{ old('sophomore', 0) }},
                    junior: {{ old('junior', 0) }},
                    get total() { return (parseInt(this.freshman)||0) + (parseInt(this.sophomore)||0) + (parseInt(this.junior)||0); },
                    advisers: {{ Js::from(old('nameOfAdviserRow', [''])) }},
                    addAdviser() { this.advisers.push(''); },
                    removeAdviser(i) { if (this.advisers.length > 1) this.advisers.splice(i, 1); },
                    presidentsByOrg: {{ Js::from($alphaPsByOrg) }},
                    presidentName: {{ Js::from($alphaPresName) }},
                    onOrgChange(el) {
                        document.getElementById('organization').value = el.options[el.selectedIndex]?.dataset.name ?? '';
                        const pName = this.presidentsByOrg[el.value] ?? '';
                        this.presidentName = pName;
                        document.getElementById('name_of_president_sig').value = pName;
                    }
                }">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">I. Basic Information</h3>
                <div class="space-y-4">
                    <div>
                        <label for="organization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if ($organizations->count() > 1)
                            <select id="organization_id" name="organization_id"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 mb-2"
                                @change="onOrgChange($el)">
                                <option value="">Select organization</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->organization_id }}" data-name="{{ $org->organization_name }}"
                                        {{ old('organization_id') == $org->organization_id ? 'selected' : '' }}>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($organizationId)
                            <input type="hidden" name="organization_id" value="{{ $organizationId }}" />
                        @endif
                        <input type="text" id="organization" name="organization"
                            placeholder="Name of student organization"
                            value="{{ old('organization', $organizations->count() === 1 ? $organizations->first()->organization_name : '') }}"
                            {{ $organizations->count() === 1 ? 'readonly' : '' }}
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('organization') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 {{ $organizations->count() === 1 ? 'bg-gray-50 dark:bg-gray-900/20' : '' }}" />
                        @error('organization') <p class="mt-1 text-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="name_of_president" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            President <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name_of_president" name="name_of_president" placeholder="Full name of president"
                            x-model="presidentName"
                            @input="document.getElementById('name_of_president_sig').value = presidentName"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('name_of_president') <p class="mt-1 text-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Faculty Adviser/s <span class="text-error-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="(adviser, index) in advisers" :key="index">
                                <div class="flex gap-2 items-center">
                                    <input type="text" :name="'nameOfAdviserRow[' + index + ']'" x-model="advisers[index]"
                                        :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeAdviser(index)" x-show="advisers.length > 1"
                                        class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addAdviser()" class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Add Adviser
                        </button>
                    </div>
                    <div>
                        <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Date of 1st Recognition</label>
                        <input type="date" id="date" name="date" value="{{ old('date') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">No. of Members</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            @foreach(['freshman','sophomore','junior'] as $yr)
                                <div>
                                    <label for="{{ $yr }}" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">{{ ucfirst($yr) }}</label>
                                    <input type="number" id="{{ $yr }}" name="{{ $yr }}" min="0" placeholder="0" x-model="{{ $yr }}"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                </div>
                            @endforeach
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Total</label>
                                <input type="number" name="total" min="0" :value="total" readonly
                                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-800 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/20 dark:text-white/90" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── SECTION 3 · OBJECTIVES ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">II. Objectives</h3>
                <textarea id="objectives" name="objectives" rows="6" placeholder="State the objectives of the organization..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('objectives') }}</textarea>
            </div>

            {{-- ── SECTION 4 · WORKPLAN (pre-selected from step 1) ─────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    selectedWorkplanId: '{{ $sessionWorkplanId }}',
                    workplanActivities: {{ Js::from($workplanActivities) }},
                    get currentActivities() { return this.workplanActivities[this.selectedWorkplanId] || []; }
                }">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">III. Workplan</h3>
                @if($finalisedWorkplans->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No finalized workplans found.</p>
                @else
                    <input type="hidden" name="workplan_id" value="{{ $sessionWorkplanId }}" />
                    <div class="mb-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                        Workplan selected in Step 1.
                        <a href="{{ route('organization-recognition') }}" class="underline hover:no-underline">Change →</a>
                    </div>
                    <div x-show="currentActivities.length > 0">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Activities in selected workplan:</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Activity</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Target Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Resources</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(activity, i) in currentActivities" :key="i">
                                        <tr class="border-t border-gray-100 dark:border-gray-800">
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="activity.title"></td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400" x-text="activity.date"></td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400" x-text="activity.resources"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ── SECTION 5 · PRESIDENT SIGNATORY ──────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                 x-data="{ changingSig: false }">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">President</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name_of_president_sig" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Printed Name</label>
                        <input type="text" id="name_of_president_sig" disabled
                            value="{{ old('name_of_president', $presidentName) }}"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/20 dark:text-gray-400" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature <span class="text-xs font-normal text-gray-400">(from step 2)</span>
                        </label>
                        <div class="space-y-2">
                            <img src="{{ asset('storage/'.$sessionSigPath) }}" alt="President Signature"
                                 class="h-16 rounded border border-gray-200 object-contain bg-white dark:border-gray-700" />
                            <button type="button" @click="changingSig = !changingSig"
                                    class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                <span x-text="changingSig ? 'Keep original' : 'Change signature'"></span>
                            </button>
                            <div x-show="changingSig">
                                <input type="file" name="signaturePresident" accept="image/jpeg,image/png"
                                       class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:outline-none dark:border-gray-700 dark:text-white/90" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Upload a new image to replace the captured signature.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── SECTION 6 · ADVISERS ──────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Adviser/s</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nameOfAdviser1" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Adviser</label>
                        <input type="text" id="nameOfAdviser1" name="nameOfAdviser1" placeholder="Adviser name" value="{{ old('nameOfAdviser1') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="nameOfAdviser2" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Adviser</label>
                        <input type="text" id="nameOfAdviser2" name="nameOfAdviser2" placeholder="Adviser name" value="{{ old('nameOfAdviser2') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- ── SECTION 7 · SUBMIT ────────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-between gap-3">
                    <a href="{{ route('accreditation.wizard.step2') }}"
                       class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        ← Back
                    </a>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit for Approval
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
