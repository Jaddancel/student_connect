@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal of Student Organization" />

    <div class="space-y-6">

        {{-- ── FORM HEADER ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <div class="mb-1 text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Application for Recognition/Renewal of Student Organization
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Fields marked <span class="text-error-500">*</span> are required.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-800 dark:bg-success-900/20 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('organization-recognition.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- ── SECTION 1 · PLEASE CHECK ────────────────────────────── --}}
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

            {{-- ── SECTION 2 · BASIC INFORMATION ───────────────────────── --}}
            @php
                $alphaPsByOrg = $presidentsByOrg;
                $alphaPresName = old('name_of_president', $presidentName);
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    freshman: {{ old('freshman', 0) }},
                    sophomore: {{ old('sophomore', 0) }},
                    junior: {{ old('junior', 0) }},
                    get total() { return (parseInt(this.freshman) || 0) + (parseInt(this.sophomore) || 0) + (parseInt(this.junior) || 0); },
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

                    {{-- Organization --}}
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
                                    <option value="{{ $org->organization_id }}"
                                        data-name="{{ $org->organization_name }}"
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
                        @error('organization')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- President --}}
                    <div>
                        <label for="name_of_president" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            President <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name_of_president" name="name_of_president"
                            placeholder="Full name of president"
                            x-model="presidentName"
                            @input="document.getElementById('name_of_president_sig').value = presidentName"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('name_of_president')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Faculty Advisers --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Faculty Adviser/s <span class="text-error-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="(adviser, index) in advisers" :key="index">
                                <div class="flex gap-2 items-center">
                                    <input type="text" :name="'nameOfAdviserRow[' + index + ']'"
                                        x-model="advisers[index]"
                                        :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeAdviser(index)"
                                        x-show="advisers.length > 1"
                                        class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addAdviser()"
                            class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Adviser
                        </button>
                        @error('nameOfAdviserRow')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date of 1st Recognition --}}
                    <div>
                        <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date of 1st Recognition
                        </label>
                        <input type="date" id="date" name="date"
                            value="{{ old('date') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Member Counts --}}
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">No. of Members</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <label for="freshman" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Freshman</label>
                                <input type="number" id="freshman" name="freshman" min="0"
                                    placeholder="0"
                                    x-model="freshman"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="sophomore" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Sophomore</label>
                                <input type="number" id="sophomore" name="sophomore" min="0"
                                    placeholder="0"
                                    x-model="sophomore"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="junior" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Junior</label>
                                <input type="number" id="junior" name="junior" min="0"
                                    placeholder="0"
                                    x-model="junior"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="total" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Total</label>
                                <input type="number" id="total" name="total" min="0"
                                    placeholder="0"
                                    :value="total"
                                    readonly
                                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-800 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/20 dark:text-white/90" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ── SECTION 3 · OBJECTIVES ───────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">II. Objectives</h3>
                <textarea id="objectives" name="objectives" rows="6"
                    placeholder="State the objectives of the organization..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('objectives') }}</textarea>
            </div>

            {{-- ── SECTION 4 · WORKPLAN ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    selectedWorkplanId: '{{ old('workplan_id', $defaultWorkplanId ?? '') }}',
                    workplanActivities: {{ Js::from($workplanActivities) }},
                    get currentActivities() {
                        return this.workplanActivities[this.selectedWorkplanId] || [];
                    }
                }">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">III. Workplan</h3>

                @if ($finalisedWorkplans->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No finalized workplans found for the current semester.</p>
                @else
                    <div class="mb-4">
                        <label for="workplan_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Select Workplan
                        </label>
                        <select id="workplan_id" name="workplan_id"
                            x-model="selectedWorkplanId"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">— No workplan selected —</option>
                            @foreach ($finalisedWorkplans as $wp)
                                <option value="{{ $wp->workplan_id }}"
                                    {{ old('workplan_id', $defaultWorkplanId) == $wp->workplan_id ? 'selected' : '' }}>
                                    {{ $wp->semester?->name ?? 'Workplan #' . $wp->workplan_id }}
                                    (finalized {{ $wp->finalized_at?->format('M d, Y') ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
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
                    <p x-show="currentActivities.length === 0 && selectedWorkplanId !== ''"
                        class="text-sm text-gray-400 dark:text-gray-500 mt-2">No approved activities found for this workplan.</p>
                @endif
            </div>

            {{-- ── SECTION 5 · PRESIDENT SIGNATORY ─────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">President</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name_of_president_sig" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Printed Name
                        </label>
                        <input type="text" id="name_of_president_sig" disabled
                            value="{{ old('name_of_president', $presidentName) }}"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/20 dark:text-gray-400" />
                    </div>
                    <div>
                        <label for="signaturePresident" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature <span class="text-error-500">*</span>
                        </label>
                        <input type="file" id="signaturePresident" name="signaturePresident"
                            accept="image/jpeg,image/png"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('signaturePresident')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- ── SECTION 6 · ADVISERS ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Adviser/s</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nameOfAdviser1" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser
                        </label>
                        <input type="text" id="nameOfAdviser1" name="nameOfAdviser1"
                            placeholder="Adviser name"
                            value="{{ old('nameOfAdviser1') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="nameOfAdviser2" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser
                        </label>
                        <input type="text" id="nameOfAdviser2" name="nameOfAdviser2"
                            placeholder="Adviser name"
                            value="{{ old('nameOfAdviser2') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- ── SECTION 7 · SUBMIT ───────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit for Approval
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
