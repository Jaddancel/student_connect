@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $isEdit ? 'Edit Organization Score' : 'Score Organization' }}" />

    @php
        $defaults = [
            'ss_seminar_college' => 0,
            'ss_seminar_univ' => 0,
            'ss_activities_related' => 0,
            'ss_activities_not_related' => 0,
            'ss_donation_cash' => 0,
            'ss_donation_kinds' => 0,
            'ss_cosponsor' => 0,
            'ss_cosponsor_count' => 2,
            'ss_income' => 0,
            'ap_other_orgs_pts' => 0,
            'ap_rep_level' => 0,
            'ap_ssc_osa_activities' => 0,
            'ap_ssc_seminars' => 0,
            'ap_other_seminars' => 0,
            'ap_osa_seminars' => 0,
            'ap_ssc_meeting' => 0,
            'ap_ssc_help' => 0,
            'aw_group_level' => 0,
            'aw_individual_level' => 0,
            'es_groups' => 0,
            'tangible_auto' => 0,
            'adm_documents' => 0,
            'adm_meetings' => 0,
            'adm_leadership' => 0,
            'adm_transparency' => 0,
        ];

        $payload = array_merge($defaults, (array) ($payload ?? []));
        $oldPayload = old('payload');
        if (is_array($oldPayload)) {
            $payload = array_merge($payload, $oldPayload);
        }
        $payload['tangible_auto'] = (int) old('payload.tangible_auto', $hasApprovedProjects ? 1 : 0);

        $pj = $payload;

        $incomeOptions = [
            0 => '0',
            500 => '500-999',
            1000 => '1000-1499',
            1500 => '1500-1999',
            2000 => '2000-2499',
            2500 => '2500-2999',
            3000 => '3000-3499',
            3500 => '3500-3999',
            4000 => '4000-4499',
            4500 => '4500-4999',
            5000 => '5000+',
        ];

        $donationKindOptions = [0, 2, 4, 6, 8, 10];
    @endphp

    <div class="space-y-6" x-data="scoringForm({{ Js::from($pj) }})">
        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $organizationName }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Semester: {{ $semester->name }}</p>
                </div>
                <a href="{{ route('admin.scoring.index', ['semester_id' => $semester->semester_id]) }}"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-900/20">
                    Back to list
                </a>
            </div>
        </div>

        <form method="POST"
            action="{{ $isEdit ? route('admin.scoring.update', $score->organization_score_id) : route('admin.scoring.store') }}"
            class="space-y-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <input type="hidden" name="organization_id" value="{{ $organization->organization_id }}" />
            <input type="hidden" name="semester_id" value="{{ $semester->semester_id }}" />
            <input type="hidden" name="payload[tangible_auto]" value="{{ $hasApprovedProjects ? 1 : 0 }}"
                x-bind:value="tangible_auto ? 1 : 0" />

            <div
                class="sticky top-4 z-10 rounded-2xl border border-gray-200 bg-white/90 p-4 shadow-sm backdrop-blur dark:border-gray-800 dark:bg-gray-950/80">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Sole</span>
                            <span><span x-text="sole"></span>/100</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-brand-500"
                                    :style="`width: ${Math.min(100, (sole / 100) * 100)}%`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Active</span>
                            <span><span x-text="active"></span>/100</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-success-500"
                                    :style="`width: ${Math.min(100, (active / 100) * 100)}%`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Awards</span>
                            <span><span x-text="awards"></span>/50</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-warning-500"
                                    :style="`width: ${Math.min(100, (awards / 50) * 100)}%`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Ext</span>
                            <span><span x-text="extension"></span>/100</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500"
                                    :style="`width: ${Math.min(100, (extension / 100) * 100)}%`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Tangible</span>
                            <span><span x-text="tangiblePts"></span>/100</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-emerald-500"
                                    :style="`width: ${Math.min(100, (tangiblePts / 100) * 100)}%`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Admin</span>
                            <span><span x-text="admin"></span>/100</span>
                            <div class="h-1.5 w-20 rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-error-500"
                                    :style="`width: ${Math.min(100, (admin / 100) * 100)}%`"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        Total: <span x-text="total.toFixed(2)"></span> / 100
                    </div>
                </div>
            </div>

            {{-- Section 1 - Sole Sponsorship --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Sole Sponsorship of
                        Programs/Activities</h3>
                    <span class="text-xs text-gray-400">Max 100 pts</span>
                </div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Seminar/Workshop (College)</p>
                        <select name="payload[ss_seminar_college]" x-model="ss_seminar_college"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ss_seminar_college || 0) * 10 + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Seminar/Workshop (University)</p>
                        <select name="payload[ss_seminar_univ]" x-model="ss_seminar_univ"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ss_seminar_univ || 0) * 15 + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Activities Related to Course</p>
                        <select name="payload[ss_activities_related]" x-model="ss_activities_related"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500"
                            x-text="Number(ss_activities_related || 0) * 10 + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Activities Not Related to Course</p>
                        <select name="payload[ss_activities_not_related]" x-model="ss_activities_not_related"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500"
                            x-text="Number(ss_activities_not_related || 0) * 7 + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Donation (Cash)</p>
                        <select name="payload[ss_donation_cash]" x-model="ss_donation_cash"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ss_donation_cash || 0) * 2 + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Donation (In Kinds)</p>
                        <select name="payload[ss_donation_kinds]" x-model="ss_donation_kinds"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach ($donationKindOptions as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ss_donation_kinds || 0) + ' pts'"></span>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/40">
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="payload[ss_cosponsor]" value="1" x-model="ss_cosponsor"
                                @checked(!empty($payload['ss_cosponsor']))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Co-sponsored with another organization
                        </label>
                        <div x-show="ss_cosponsor" x-cloak
                            class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                            <p class="text-sm text-gray-700 dark:text-gray-300">Number of orgs involved</p>
                            <select name="payload[ss_cosponsor_count]" x-model="ss_cosponsor_count"
                                class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                                @foreach (range(2, 10) as $i)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endforeach
                            </select>
                            <span class="text-xs text-gray-500"
                                x-text="ss_cosponsor ? Math.floor(10 / Math.max(2, Number(ss_cosponsor_count || 2))) + ' pts' : '0 pts'"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Income Generated</p>
                        <select name="payload[ss_income]" x-model="ss_income"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach ($incomeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500"
                            x-text="Math.floor(Number(ss_income || 0) / 500) + ' pts'"></span>
                    </div>
                </div>
            </div>

            {{-- Section 2 - Active Participation --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Active Participation</h3>
                    <span class="text-xs text-gray-400">Max 100 pts</span>
                </div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Participants in other organizations</p>
                        <select name="payload[ap_other_orgs_pts]" x-model="ap_other_orgs_pts"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            @foreach (range(0, 10) as $i)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ap_other_orgs_pts || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Representative level</p>
                        <select name="payload[ap_rep_level]" x-model="ap_rep_level"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="0">None</option>
                            <option value="2">Local</option>
                            <option value="4">Provincial</option>
                            <option value="6">Regional</option>
                            <option value="8">National</option>
                            <option value="10">International</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ap_rep_level || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">SSC-OSA activities</p>
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="payload[ap_ssc_osa_activities]" value="1"
                                x-model="ap_ssc_osa_activities" @checked(!empty($payload['ap_ssc_osa_activities']))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Participated
                        </label>
                        <span class="text-xs text-gray-500" x-text="ap_ssc_osa_activities ? '10 pts' : '0 pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">SSC seminars</p>
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="payload[ap_ssc_seminars]" value="1"
                                x-model="ap_ssc_seminars" @checked(!empty($payload['ap_ssc_seminars']))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Participated
                        </label>
                        <span class="text-xs text-gray-500" x-text="ap_ssc_seminars ? '10 pts' : '0 pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Other seminars</p>
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="payload[ap_other_seminars]" value="1"
                                x-model="ap_other_seminars" @checked(!empty($payload['ap_other_seminars']))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Participated
                        </label>
                        <span class="text-xs text-gray-500" x-text="ap_other_seminars ? '7 pts' : '0 pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">OSA seminars</p>
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="payload[ap_osa_seminars]" value="1"
                                x-model="ap_osa_seminars" @checked(!empty($payload['ap_osa_seminars']))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Participated
                        </label>
                        <span class="text-xs text-gray-500" x-text="ap_osa_seminars ? '5 pts' : '0 pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">SSC meeting attendance</p>
                        <select name="payload[ap_ssc_meeting]" x-model="ap_ssc_meeting"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="0">None</option>
                            <option value="1">Proxy</option>
                            <option value="2">Authorized Rep</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ap_ssc_meeting || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Help rendered</p>
                        <select name="payload[ap_ssc_help]" x-model="ap_ssc_help"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="0">None</option>
                            <option value="3">Others</option>
                            <option value="5">SSC-OSA</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(ap_ssc_help || 0) + ' pts'"></span>
                    </div>
                </div>
            </div>

            {{-- Section 3 - Awards --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Awards / Achievements</h3>
                    <span class="text-xs text-gray-400">Max 50 pts</span>
                </div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Group award level</p>
                        <select name="payload[aw_group_level]" x-model="aw_group_level"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="0">None</option>
                            <option value="3">Local</option>
                            <option value="5">Provincial</option>
                            <option value="7">Regional</option>
                            <option value="10">National</option>
                            <option value="20">International</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(aw_group_level || 0) + ' pts'"></span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Individual award level</p>
                        <select name="payload[aw_individual_level]" x-model="aw_individual_level"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="0">None</option>
                            <option value="1">Local</option>
                            <option value="2">Provincial</option>
                            <option value="5">Regional</option>
                            <option value="7">National</option>
                            <option value="10">International</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(aw_individual_level || 0) + ' pts'"></span>
                    </div>
                </div>
            </div>

            {{-- Section 4 - Extension Services --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Extension Services / Community
                        Outreach</h3>
                    <span class="text-xs text-gray-400">Max 100 pts</span>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                    <p class="text-sm text-gray-700 dark:text-gray-300">Groups with at least 10 members</p>
                    <select name="payload[es_groups]" x-model="es_groups"
                        class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                        @foreach (range(0, 10) as $i)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-500" x-text="Number(es_groups || 0) * 10 + ' pts'"></span>
                </div>
            </div>

            {{-- Section 5 - Tangible Projects --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Tangible Projects</h3>
                    <span class="text-xs text-gray-400">Auto-calculated</span>
                </div>
                <div class="mt-3">
                    @if ($hasApprovedProjects)
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Auto-credited
                            (20 pts)</span>
                    @else
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">No
                            approved project requests found (0 pts)</span>
                    @endif
                </div>
            </div>

            {{-- Section 6 - Administrative Work --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Administrative Work (Deduction)
                    </h3>
                    <span class="text-xs text-gray-400">Max 100 pts</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Starts at 100. Select the level that applies to
                    deduct accordingly.</p>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Documents submitted</p>
                        <select name="payload[adm_documents]" x-model="adm_documents"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="50">Full (50)</option>
                            <option value="40">Minor Issues (40)</option>
                            <option value="25">Moderate (25)</option>
                            <option value="10">Major (10)</option>
                            <option value="0">Non-compliant (0)</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(adm_documents || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Meetings attended</p>
                        <select name="payload[adm_meetings]" x-model="adm_meetings"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="25">Full (25)</option>
                            <option value="15">Partial (15)</option>
                            <option value="5">Minimal (5)</option>
                            <option value="0">None (0)</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(adm_meetings || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Leadership quality</p>
                        <select name="payload[adm_leadership]" x-model="adm_leadership"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="15">Excellent (15)</option>
                            <option value="10">Good (10)</option>
                            <option value="5">Fair (5)</option>
                            <option value="0">Poor (0)</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(adm_leadership || 0) + ' pts'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_180px_120px]">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Transparency</p>
                        <select name="payload[adm_transparency]" x-model="adm_transparency"
                            class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            <option value="10">Full (10)</option>
                            <option value="5">Partial (5)</option>
                            <option value="0">None (0)</option>
                        </select>
                        <span class="text-xs text-gray-500" x-text="Number(adm_transparency || 0) + ' pts'"></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('admin.scoring.index', ['semester_id' => $semester->semester_id]) }}"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-900/20">
                    Cancel
                </a>
                <button type="submit"
                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                    {{ $isEdit ? 'Update Score' : 'Save Score' }}
                </button>
            </div>
        </form>
    </div>

    <script>
        function scoringForm(init) {
            const toNum = (value) => Number(value || 0);
            const toBool = (value) => Boolean(Number(value));

            return {
                ...init,
                ss_cosponsor: toBool(init.ss_cosponsor),
                ap_ssc_osa_activities: toBool(init.ap_ssc_osa_activities),
                ap_ssc_seminars: toBool(init.ap_ssc_seminars),
                ap_other_seminars: toBool(init.ap_other_seminars),
                ap_osa_seminars: toBool(init.ap_osa_seminars),
                tangible_auto: toBool(init.tangible_auto),
                get sole() {
                    const cospts = this.ss_cosponsor ?
                        Math.floor(10 / Math.max(2, toNum(this.ss_cosponsor_count || 2))) :
                        0;
                    return Math.min(100,
                        toNum(this.ss_seminar_college) * 10 +
                        toNum(this.ss_seminar_univ) * 15 +
                        toNum(this.ss_activities_related) * 10 +
                        toNum(this.ss_activities_not_related) * 7 +
                        toNum(this.ss_donation_cash) * 2 +
                        toNum(this.ss_donation_kinds) +
                        cospts +
                        Math.floor(toNum(this.ss_income) / 500)
                    );
                },
                get active() {
                    return Math.min(100,
                        toNum(this.ap_other_orgs_pts) +
                        toNum(this.ap_rep_level) +
                        (this.ap_ssc_osa_activities ? 10 : 0) +
                        (this.ap_ssc_seminars ? 10 : 0) +
                        (this.ap_other_seminars ? 7 : 0) +
                        (this.ap_osa_seminars ? 5 : 0) +
                        toNum(this.ap_ssc_meeting) +
                        toNum(this.ap_ssc_help)
                    );
                },
                get awards() {
                    return Math.min(50, toNum(this.aw_group_level) + toNum(this.aw_individual_level));
                },
                get extension() {
                    return Math.min(100, toNum(this.es_groups) * 10);
                },
                get tangiblePts() {
                    return this.tangible_auto ? 100 : 0;
                },
                get admin() {
                    return toNum(this.adm_documents) +
                        toNum(this.adm_meetings) +
                        toNum(this.adm_leadership) +
                        toNum(this.adm_transparency);
                },
                get total() {
                    return Number((
                        (this.sole / 100 * 20) +
                        (this.active / 100 * 20) +
                        (this.awards / 50 * 5) +
                        (this.extension / 100 * 15) +
                        (this.tangiblePts / 100 * 20) +
                        (this.admin / 100 * 20)
                    ).toFixed(2));
                },
            };
        }
    </script>
@endsection
