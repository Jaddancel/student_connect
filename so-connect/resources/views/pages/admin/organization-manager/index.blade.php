@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Manager" />

    <div class="space-y-6" x-data="adminOrganizationOfficerChange({ candidatesUrl: @js($selectedOrganizationId > 0 ? route('admin.organization-manager.candidates', ['organization' => $selectedOrganizationId]) : '') })">

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Organization picker --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            @if ($organizations->isEmpty() && ! $typeFilter)
                <p class="text-sm text-gray-500 dark:text-gray-400">There are no active organizations to manage.</p>
            @else
                <form method="GET" action="{{ route('admin.organization-manager.index') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="w-full md:max-w-xs">
                        <label for="organization_type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization Type</label>
                        <select id="organization_type" name="organization_type" data-autosubmit
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">All organization types</option>
                            @foreach ($typeOptions as $typeId => $typeLabel)
                                <option value="{{ $typeId }}" @selected($typeFilter === $typeId)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full md:max-w-md">
                        <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                        @if ($organizations->isEmpty())
                            <p class="flex h-11 items-center text-sm text-gray-500 dark:text-gray-400">
                                No active {{ $typeOptions[$typeFilter] ?? '' }} organizations.
                            </p>
                        @else
                            <select id="organization_id" name="organization_id" data-autosubmit
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization->organization_id }}" @selected($selectedOrganizationId === $organization->organization_id)>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <noscript>
                        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Load</button>
                    </noscript>
                </form>
            @endif
        </div>

        @if ($board)
            {{-- Named officer positions --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 lg:px-6">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Officers</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-5 py-3 lg:px-6">Position</th>
                                <th class="px-5 py-3 lg:px-6">Officer</th>
                                <th class="px-5 py-3 text-right lg:px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($board['positions'] as $row)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800" data-position-row="{{ $row['position'] }}">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-white/90 lg:px-6">{{ $row['position'] }}</td>
                                    <td class="px-5 py-4 lg:px-6">
                                        @if ($row['holder'])
                                            <p class="text-gray-800 dark:text-white/90">{{ $row['holder']['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['holder']['email'] }}</p>
                                        @else
                                            <span class="text-gray-400 italic dark:text-gray-500">Vacant</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right lg:px-6">
                                        <button type="button"
                                            data-position="{{ $row['position'] }}"
                                            data-holder="{{ $row['holder']['name'] ?? '' }}"
                                            @click="openFor($event)"
                                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                            Change
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Other officers (read-only) --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 lg:px-6">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Other</h3>
                </div>
                @if (empty($board['others']))
                    <p class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400 lg:px-6">No other officers.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($board['others'] as $other)
                            <li class="px-5 py-3 lg:px-6" data-other-officer>
                                <p class="text-sm text-gray-800 dark:text-white/90">{{ $other['name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $other['email'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Change Officer modal --}}
            <div x-show="open" x-cloak x-transition.opacity
                class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" aria-labelledby="change-officer-title"
                @keydown.escape.window="close()">
                <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
                    @click.outside="close()">
                    <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 id="change-officer-title" class="text-base font-semibold text-gray-800 dark:text-white/90">Change Officer</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="position"></span>
                                <span x-show="currentHolder !== ''">· currently <span x-text="currentHolder"></span></span>
                                <span x-show="currentHolder === ''">· vacant</span>
                            </p>
                        </div>
                        <button type="button" @click="close()" aria-label="Close"
                            class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-200">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.organization-manager.update', ['organization' => $selectedOrganizationId]) }}" class="space-y-4 px-5 py-4">
                        @csrf
                        <input type="hidden" name="position" :value="position">
                        <input type="hidden" name="user_id" :value="selectedId">

                        <div>
                            <label for="officer-search" class="sr-only">Search members</label>
                            <input id="officer-search" type="search" x-ref="search" x-model="query" @input="onSearchInput()"
                                placeholder="Search members by name or email" autocomplete="off"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>

                        <div class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-800">
                            <p x-show="loading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Searching…</p>
                            <p x-show="error !== ''" x-text="error" class="px-4 py-3 text-sm text-error-600 dark:text-error-400"></p>
                            <p x-show="showEmpty" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No matching officer-account members.</p>
                            <ul x-show="!loading" class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="candidate in results" :key="candidate.user_id">
                                    <li>
                                        <button type="button" @click="choose(candidate)" :disabled="isCurrent(candidate)"
                                            :aria-pressed="isSelected(candidate)"
                                            :class="isSelected(candidate) ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left disabled:cursor-not-allowed disabled:opacity-50">
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm text-gray-800 dark:text-white/90" x-text="candidate.name"></span>
                                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="candidate.email"></span>
                                            </span>
                                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300" x-text="candidate.current"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <p x-show="canSubmit" class="text-sm text-gray-600 dark:text-gray-300">
                            Assign <strong x-text="selectedName"></strong> as <strong x-text="position"></strong>.
                            The current holder, if any, stays an officer under “Other”.
                        </p>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="!canSubmit"
                                class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                                Change
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
