@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $isEdit ? 'Edit Organization Score' : 'Score Organization' }}" />

    @php
        $pv = fn (string $key) => (int) ($payload[$key] ?? 0);
        $oldPayload = old('payload');
        if (is_array($oldPayload)) {
            foreach ($oldPayload as $k => $v) {
                $payload[$k] = $v;
            }
        }
    @endphp

    <div class="space-y-5">

        {{-- Header card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $organizationName }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $semester->name }}</p>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST"
            action="{{ $isEdit ? route('admin.scoring.update', $score->organization_score_id) : route('admin.scoring.store') }}"
            class="space-y-5"
            x-data="{
                cat1_seminar_college:        {{ $pv('cat1_seminar_college') }},
                cat1_seminar_univ:           {{ $pv('cat1_seminar_univ') }},
                cat1_activities_related:     {{ $pv('cat1_activities_related') }},
                cat1_activities_not_related: {{ $pv('cat1_activities_not_related') }},
                cat1_donation_cash:          {{ $pv('cat1_donation_cash') }},
                cat1_donation_kinds:         {{ $pv('cat1_donation_kinds') }},
                cat1_cosponsor_pts:          {{ $pv('cat1_cosponsor_pts') }},
                cat1_income:                 {{ $pv('cat1_income') }},

                cat2_other_orgs:             {{ $pv('cat2_other_orgs') }},
                cat2_rep_local:              {{ $pv('cat2_rep_local') }},
                cat2_rep_provincial:         {{ $pv('cat2_rep_provincial') }},
                cat2_rep_regional:           {{ $pv('cat2_rep_regional') }},
                cat2_rep_national:           {{ $pv('cat2_rep_national') }},
                cat2_rep_international:      {{ $pv('cat2_rep_international') }},
                cat2_ssc_osa_activities:     {{ $pv('cat2_ssc_osa_activities') }},
                cat2_ssc_seminars:           {{ $pv('cat2_ssc_seminars') }},
                cat2_other_seminars:         {{ $pv('cat2_other_seminars') }},
                cat2_osa_seminars:           {{ $pv('cat2_osa_seminars') }},
                cat2_ssc_meeting_rep:        {{ $pv('cat2_ssc_meeting_rep') }},
                cat2_ssc_meeting_proxy:      {{ $pv('cat2_ssc_meeting_proxy') }},
                cat2_help_ssc_osa:           {{ $pv('cat2_help_ssc_osa') }},
                cat2_help_others:            {{ $pv('cat2_help_others') }},

                cat3_group_intl:             {{ $pv('cat3_group_intl') }},
                cat3_group_national:         {{ $pv('cat3_group_national') }},
                cat3_group_regional:         {{ $pv('cat3_group_regional') }},
                cat3_group_provincial:       {{ $pv('cat3_group_provincial') }},
                cat3_group_local:            {{ $pv('cat3_group_local') }},
                cat3_individual_intl:        {{ $pv('cat3_individual_intl') }},
                cat3_individual_national:    {{ $pv('cat3_individual_national') }},
                cat3_individual_regional:    {{ $pv('cat3_individual_regional') }},
                cat3_individual_provincial:  {{ $pv('cat3_individual_provincial') }},
                cat3_individual_local:       {{ $pv('cat3_individual_local') }},

                cat4_extension_groups:       {{ $pv('cat4_extension_groups') }},

                cat5_tangible_projects:      {{ $pv('cat5_tangible_projects') }},

                cat6_documents:              {{ $pv('cat6_documents') }},
                cat6_meetings:               {{ $pv('cat6_meetings') }},
                cat6_leadership:             {{ $pv('cat6_leadership') }},
                cat6_transparency:           {{ $pv('cat6_transparency') }},

                n(v) { return Math.max(0, parseInt(v) || 0); },

                get cat1_total() {
                    return Math.min(100,
                        this.n(this.cat1_seminar_college) * 10 +
                        this.n(this.cat1_seminar_univ) * 15 +
                        this.n(this.cat1_activities_related) * 10 +
                        this.n(this.cat1_activities_not_related) * 7 +
                        this.n(this.cat1_donation_cash) * 2 +
                        this.n(this.cat1_donation_kinds) * 10 +
                        this.n(this.cat1_cosponsor_pts) +
                        this.n(this.cat1_income)
                    );
                },
                get cat2_total() {
                    return Math.min(100,
                        this.n(this.cat2_other_orgs) * 10 +
                        this.n(this.cat2_rep_local) * 2 +
                        this.n(this.cat2_rep_provincial) * 3 +
                        this.n(this.cat2_rep_regional) * 5 +
                        this.n(this.cat2_rep_national) * 7 +
                        this.n(this.cat2_rep_international) * 10 +
                        this.n(this.cat2_ssc_osa_activities) * 10 +
                        this.n(this.cat2_ssc_seminars) * 10 +
                        this.n(this.cat2_other_seminars) * 7 +
                        this.n(this.cat2_osa_seminars) * 5 +
                        this.n(this.cat2_ssc_meeting_rep) * 2 +
                        this.n(this.cat2_ssc_meeting_proxy) * 1 +
                        this.n(this.cat2_help_ssc_osa) * 5 +
                        this.n(this.cat2_help_others) * 3
                    );
                },
                get cat3_total() {
                    return Math.min(50,
                        this.n(this.cat3_group_intl) * 20 +
                        this.n(this.cat3_group_national) * 15 +
                        this.n(this.cat3_group_regional) * 10 +
                        this.n(this.cat3_group_provincial) * 7 +
                        this.n(this.cat3_group_local) * 5 +
                        this.n(this.cat3_individual_intl) * 15 +
                        this.n(this.cat3_individual_national) * 10 +
                        this.n(this.cat3_individual_regional) * 7 +
                        this.n(this.cat3_individual_provincial) * 5 +
                        this.n(this.cat3_individual_local) * 3
                    );
                },
                get cat4_total() {
                    return Math.min(100, this.n(this.cat4_extension_groups) * 10);
                },
                get cat5_total() {
                    return Math.min(100, this.n(this.cat5_tangible_projects) * 100);
                },
                get cat6_total() {
                    return Math.min(100,
                        this.n(this.cat6_documents) * 50 +
                        this.n(this.cat6_meetings) * 25 +
                        this.n(this.cat6_leadership) * 15 +
                        this.n(this.cat6_transparency) * 10
                    );
                },
                get grand_total() {
                    return this.cat1_total + this.cat2_total + this.cat3_total + this.cat4_total + this.cat5_total + this.cat6_total;
                },
            }">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <input type="hidden" name="organization_id" value="{{ $organization->organization_id }}" />
            <input type="hidden" name="semester_id" value="{{ $semester->semester_id }}" />

            {{-- CATEGORY I --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category I — Sole Activities</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 100 pts</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat1_total + ' / 100'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts/Instance</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Instances</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            @php
                            $cat1rows = [
                                ['cat1_seminar_college',        'Seminars – College Level (≥15 members)',              10,  false],
                                ['cat1_seminar_univ',           'Seminars – University Level (≥30 members)',           15,  false],
                                ['cat1_activities_related',     'Activities Related to Org (≥15 members)',             10,  false],
                                ['cat1_activities_not_related', 'Activities Not Related to Org (≥15 members)',          7,  false],
                                ['cat1_donation_cash',          'Donation – Cash (per ₱200)',                           2,  true],
                                ['cat1_donation_kinds',         'Donation – In Kind (binary 0/1)',                     10,  true],
                                ['cat1_cosponsor_pts',          'Co-sponsorship Points (pre-computed)',                 1,  false],
                                ['cat1_income',                 'Income Generated (per ₱500)',                          1,  true],
                            ];
                            @endphp
                            @foreach ($cat1rows as [$field, $label, $ppi, $manual])
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $label }}
                                        @if ($manual)
                                            <span class="ml-1 text-xs text-gray-400">(manual)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ $ppi }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" name="payload[{{ $field }}]"
                                            x-model="{{ $field }}" min="0"
                                            class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                        x-text="n({{ $field }}) * {{ $ppi }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category I Total (capped at 100)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat1_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- CATEGORY II --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category II — Active Participation</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 100 pts</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat2_total + ' / 100'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts/Instance</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Instances</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            @php
                            $cat2rows = [
                                ['cat2_other_orgs',          'Activities co-sponsored by other orgs',                10, false],
                                ['cat2_rep_local',           'Representative – Local scope',                         2,  false],
                                ['cat2_rep_provincial',      'Representative – Provincial scope',                    3,  false],
                                ['cat2_rep_regional',        'Representative – Regional scope',                      5,  false],
                                ['cat2_rep_national',        'Representative – National scope',                      7,  false],
                                ['cat2_rep_international',   'Representative – International scope',                10,  false],
                                ['cat2_ssc_osa_activities',  'SSC/OSA-sponsored activities',                        10,  false],
                                ['cat2_ssc_seminars',        'SSC-sponsored seminars',                              10,  false],
                                ['cat2_other_seminars',      'Other org seminars/conferences',                       7,  false],
                                ['cat2_osa_seminars',        'OSA/Admin seminars',                                   5,  false],
                                ['cat2_ssc_meeting_rep',     'SSC meeting – Representative',                         2,  true],
                                ['cat2_ssc_meeting_proxy',   'SSC meeting – Proxy',                                  1,  true],
                                ['cat2_help_ssc_osa',        'Preparation/help for SSC/OSA',                         5,  false],
                                ['cat2_help_others',         'Preparation/help for other orgs',                      3,  false],
                            ];
                            @endphp
                            @foreach ($cat2rows as [$field, $label, $ppi, $manual])
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $label }}
                                        @if ($manual)
                                            <span class="ml-1 text-xs text-gray-400">(manual)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ $ppi }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" name="payload[{{ $field }}]"
                                            x-model="{{ $field }}" min="0"
                                            class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                        x-text="n({{ $field }}) * {{ $ppi }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category II Total (capped at 100)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat2_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- CATEGORY III --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category III — Awards & Recognition</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 50 pts</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat3_total + ' / 50'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts/Instance</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Instances</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            @php
                            $cat3rows = [
                                ['cat3_group_intl',           'Group Award – International',   20],
                                ['cat3_group_national',       'Group Award – National',        15],
                                ['cat3_group_regional',       'Group Award – Regional',        10],
                                ['cat3_group_provincial',     'Group Award – Provincial',       7],
                                ['cat3_group_local',          'Group Award – Local',            5],
                                ['cat3_individual_intl',      'Individual Award – International', 15],
                                ['cat3_individual_national',  'Individual Award – National',    10],
                                ['cat3_individual_regional',  'Individual Award – Regional',     7],
                                ['cat3_individual_provincial','Individual Award – Provincial',   5],
                                ['cat3_individual_local',     'Individual Award – Local',        3],
                            ];
                            @endphp
                            @foreach ($cat3rows as [$field, $label, $ppi])
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $label }}</td>
                                    <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ $ppi }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" name="payload[{{ $field }}]"
                                            x-model="{{ $field }}" min="0"
                                            class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                        x-text="n({{ $field }}) * {{ $ppi }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category III Total (capped at 50)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat3_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- CATEGORY IV --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category IV — Extension Services</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 100 pts</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat4_total + ' / 100'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts/Instance</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Instances</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">Extension Service Groups (≥10 members)</td>
                                <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">10</td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" name="payload[cat4_extension_groups]"
                                        x-model="cat4_extension_groups" min="0"
                                        class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                    x-text="n(cat4_extension_groups) * 10"></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category IV Total (capped at 100)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat4_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- CATEGORY V --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category V — Tangible Projects</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 100 pts (×100 per project, capped)</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat5_total + ' / 100'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts/Instance</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Instances</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">Approved Tangible Projects</td>
                                <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">100</td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" name="payload[cat5_tangible_projects]"
                                        x-model="cat5_tangible_projects" min="0"
                                        class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                    x-text="Math.min(100, n(cat5_tangible_projects) * 100)"></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category V Total (capped at 100)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat5_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- CATEGORY VI --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Category VI — Administrative</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Max 100 pts (binary fields: enter 1 for yes, 0 for no)</p>
                        </div>
                        <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="cat6_total + ' / 100'"></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2.5 text-left font-semibold">Indicator</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-24">Pts</th>
                                <th class="px-4 py-2.5 text-center font-semibold w-28">Value (0/1)</th>
                                <th class="px-4 py-2.5 text-right font-semibold w-24">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            @php
                            $cat6rows = [
                                ['cat6_documents',    'Required Documents Submitted',          50, false],
                                ['cat6_meetings',     'General Meetings with Minutes (>30 min)',25, false],
                                ['cat6_leadership',   'Leadership Training Participated',       15, true],
                                ['cat6_transparency', 'Financial/Transparency Report Submitted',10, false],
                            ];
                            @endphp
                            @foreach ($cat6rows as [$field, $label, $ppi, $manual])
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $label }}
                                        @if ($manual)
                                            <span class="ml-1 text-xs text-gray-400">(manual)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ $ppi }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" name="payload[{{ $field }}]"
                                            x-model="{{ $field }}" min="0" max="1"
                                            class="h-8 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm text-gray-800 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300"
                                        x-text="Math.min(1, n({{ $field }})) * {{ $ppi }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02]">
                                <td colspan="3" class="px-6 py-3 text-xs font-semibold text-gray-600 dark:text-gray-400">Category VI Total (capped at 100)</td>
                                <td class="px-4 py-3 text-right font-bold text-brand-600 dark:text-brand-400" x-text="cat6_total"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Grand Total --}}
            <div class="rounded-2xl border-2 border-brand-300 bg-brand-50 p-5 dark:border-brand-700/50 dark:bg-brand-900/10">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Grand Total</p>
                    <p class="text-2xl font-bold text-brand-600 dark:text-brand-400" x-text="grand_total + ' / 550'"></p>
                </div>
                <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-300"
                        :style="'width:' + Math.min(100, Math.round(grand_total / 550 * 100)) + '%'"></div>
                </div>
                <p class="mt-1 text-right text-xs text-gray-400" x-text="Math.min(100, Math.round(grand_total / 550 * 100)) + '% of max'"></p>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.scoring.index', ['semester_id' => $semester->semester_id]) }}"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancel
                </a>
                <button type="submit"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    {{ $isEdit ? 'Update Score' : 'Save Score' }}
                </button>
            </div>
        </form>
    </div>
@endsection
