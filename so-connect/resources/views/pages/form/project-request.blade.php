@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Project Request" />

    <div class="space-y-6">
        @if (session('success'))
            <div
                class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div
            class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-center text-base font-bold uppercase tracking-wide text-gray-900 dark:text-white">Republic of the
                Philippines</h2>
            <p class="mt-0.5 text-center text-sm font-semibold text-gray-800 dark:text-white/90">TARLAC AGRICULTURAL
                UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="mt-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300">OFFICE OF STUDENT SERVICES AND
                DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="mt-3 text-center text-base font-bold uppercase tracking-widest text-gray-900 dark:text-white">Project
                Request</p>
        </div>

        <form action="{{ route('project-request.store') }}" method="POST" class="space-y-6"
            x-data="{
                isDonation: {{ old('is_donation') ? 'true' : 'false' }},
                inKinds: {{ Js::from(array_values(array_filter((array) old('in_kinds', ['']), fn($v) => $v !== null))) }},
                addKind() { this.inKinds.push('') },
                removeKind(i) { if (this.inKinds.length > 1) this.inKinds.splice(i, 1) }
            }">
            @csrf

            {{-- Project Type --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Project Type</h3>

                <div class="flex flex-wrap gap-4">
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <input type="radio" name="is_donation" value="0"
                            :checked="!isDonation"
                            @change="isDonation = false"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Project</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <input type="radio" name="is_donation" value="1"
                            :checked="isDonation"
                            @change="isDonation = true"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Donation</span>
                    </label>
                </div>

                {{-- Donation Amount --}}
                <div x-show="isDonation" x-transition class="mt-4">
                    <label for="donation_amount" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Expected Donation Amount (₱) <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-gray-500 dark:text-gray-400">₱</span>
                        <input type="number" id="donation_amount" name="donation_amount"
                            value="{{ old('donation_amount') }}"
                            min="0" step="0.01"
                            placeholder="0.00"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('donation_amount') ? 'border-error-500' : 'border-gray-300' }} bg-transparent py-2.5 pr-4 pl-8 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    @error('donation_amount')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- In-Kinds Beneficiaries --}}
                <div x-show="isDonation" x-transition class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        In-Kind Beneficiaries
                        <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(optional — each row counts toward scoring)</span>
                    </label>
                    <div class="space-y-2">
                        <template x-for="(row, i) in inKinds" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="text" name="in_kinds[]"
                                    :value="row"
                                    @input="inKinds[i] = $event.target.value"
                                    placeholder="Beneficiary name or description"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                <button type="button" @click="removeKind(i)"
                                    x-show="inKinds.length > 1"
                                    class="shrink-0 rounded-lg border border-gray-300 p-2.5 text-gray-400 transition hover:border-error-400 hover:text-error-500 dark:border-gray-700 dark:text-gray-500">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addKind()"
                        class="mt-2 flex items-center gap-1.5 text-sm text-brand-500 transition hover:text-brand-600">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add beneficiary
                    </button>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if ($organizations->count() > 1)
                            <select name="organization_id"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('organization_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select organization</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->organization_id }}" @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" value="{{ $organizations->first()?->organization_name ?? '' }}"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id"
                                value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Project Details</h3>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="projectTitle" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Project Title <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="projectTitle" name="projectTitle" value="{{ old('projectTitle') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('projectTitle') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="Title of the project or letter" />
                    </div>

                    <div>
                        <label for="natureOfProject"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nature of Project <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="natureOfProject" name="natureOfProject"
                            value="{{ old('natureOfProject') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('natureOfProject') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="e.g. Outreach, Research, Community Service" />
                    </div>

                    <div>
                        <label for="projectArea" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Project Area <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="projectArea" name="projectArea" value="{{ old('projectArea') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('projectArea') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="e.g. Campus, Barangay, Municipality" />
                    </div>

                    <div>
                        <label for="letterOfIntent"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Letter of Intent <span class="text-error-500">*</span>
                        </label>
                        <textarea id="letterOfIntent" name="letterOfIntent" rows="10"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border {{ $errors->has('letterOfIntent') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            placeholder="Write the intent of the project here">{{ old('letterOfIntent') }}</textarea>
                    </div>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
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
