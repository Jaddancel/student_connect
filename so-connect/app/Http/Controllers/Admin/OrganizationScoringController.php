<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Organization;
use App\Models\OrganizationScore;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationScoringController extends Controller
{
    private const CATEGORY_LABELS = [
        OrganizationType::SOCIO_CIVIC             => 'Socio-Civic',
        OrganizationType::RELIGIOUS               => 'Religious',
        OrganizationType::FRATERNITIES_SORORITIES => 'Fraternities-Sororities',
        OrganizationType::SPECIAL_INTEREST        => 'Special Interest',
        OrganizationType::STUDENT_GOVERNMENT      => 'Student Government',
    ];

    public function index(Request $request)
    {
        $semesters = Semester::query()->orderByDesc('starts_at')->get();

        $selectedSemesterId = $request->integer('semester_id');
        if (! $selectedSemesterId) {
            $selectedSemesterId = Semester::current()?->semester_id;
        }

        $selectedSemester = $selectedSemesterId
            ? $semesters->firstWhere('semester_id', $selectedSemesterId)
            : null;

        $categoryFilter = $request->integer('category');
        $groupedRows = collect();

        if ($selectedSemesterId) {
            $orgQuery = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('o.organization_type', '!=', OrganizationType::UNIVERSITY_SANCTIONED)
                ->select('o.organization_id', 'o.organization_type', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->orderBy('o.organization_type')
                ->orderBy('name');

            if ($categoryFilter) {
                $orgQuery->where('o.organization_type', $categoryFilter);
            }

            $organizations = $orgQuery->get();

            $scores = OrganizationScore::query()
                ->where('semester_id', $selectedSemesterId)
                ->get()
                ->keyBy('organization_id');

            $rows = $organizations->map(fn ($organization) => [
                'organization_id'   => (int) $organization->organization_id,
                'organization_name' => $organization->name,
                'organization_type' => (int) $organization->organization_type,
                'score'             => $scores->get($organization->organization_id),
            ]);

            $groupedRows = $rows->groupBy('organization_type');
        }

        return view('pages.admin.scoring.index', [
            'title'              => 'Organization Scoring',
            'semesters'          => $semesters,
            'selectedSemester'   => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'groupedRows'        => $groupedRows,
            'categoryLabels'     => self::CATEGORY_LABELS,
            'categoryFilter'     => $categoryFilter,
            'rows'               => $groupedRows->flatten(1),
        ]);
    }

    public function create(Request $request)
    {
        $organizationId = $request->integer('organization_id');
        $semesterId = $request->integer('semester_id');

        if (! $organizationId || ! $semesterId) {
            return redirect()->route('admin.scoring.index')
                ->withErrors(['selection' => 'Select an organization and semester first.']);
        }

        $existing = OrganizationScore::query()
            ->where('organization_id', $organizationId)
            ->where('semester_id', $semesterId)
            ->first();

        if ($existing) {
            return redirect()->route('admin.scoring.edit', $existing->organization_score_id);
        }

        $organization = Organization::query()->findOrFail($organizationId);
        $organizationName = DB::table('organization_details')
            ->where('organization_detail_id', $organization->getAttribute('detail'))
            ->value('name') ?? 'Unknown Organization';
        $semester = Semester::query()->findOrFail($semesterId);

        return view('pages.admin.scoring.create', [
            'title'            => 'Score Organization',
            'organization'     => $organization,
            'organizationName' => $organizationName,
            'semester'         => $semester,
            'payload'          => $this->computeAutoInstances($organizationId, $semester),
            'score'            => null,
            'isEdit'           => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $organizationId = (int) $validated['organization_id'];
        $semesterId     = (int) $validated['semester_id'];

        $existing = OrganizationScore::query()
            ->where('organization_id', $organizationId)
            ->where('semester_id', $semesterId)
            ->first();

        if ($existing) {
            return redirect()->route('admin.scoring.edit', $existing->organization_score_id);
        }

        $payload = $this->normalizePayload($validated['payload'] ?? []);
        $scores  = $this->computeScores($payload);

        OrganizationScore::query()->create([
            'organization_id'     => $organizationId,
            'semester_id'         => $semesterId,
            'scored_by'           => $request->user()?->user_id,
            'payload'             => $payload,
            'raw_scores'          => $scores,
            'total_weighted_score' => $scores['total'],
            'scored_at'           => now(),
        ]);

        return redirect()->route('admin.scoring.index', ['semester_id' => $semesterId])
            ->with('success', 'Organization score saved.');
    }

    public function edit(int $id)
    {
        $score        = OrganizationScore::query()->findOrFail($id);
        $organization = Organization::query()->findOrFail($score->organization_id);
        $organizationName = DB::table('organization_details')
            ->where('organization_detail_id', $organization->getAttribute('detail'))
            ->value('name') ?? 'Unknown Organization';
        $semester     = Semester::query()->findOrFail($score->semester_id);

        $autoInstances  = $this->computeAutoInstances($score->organization_id, $semester);
        $existingPayload = array_filter((array) ($score->payload ?? []), fn ($v) => $v !== null && $v !== '');
        $mergedPayload  = array_merge($autoInstances, $existingPayload);

        return view('pages.admin.scoring.create', [
            'title'            => 'Edit Organization Score',
            'organization'     => $organization,
            'organizationName' => $organizationName,
            'semester'         => $semester,
            'payload'          => $mergedPayload,
            'score'            => $score,
            'isEdit'           => true,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $score     = OrganizationScore::query()->findOrFail($id);
        $validated = $this->validatePayload($request);

        if ((int) $validated['organization_id'] !== (int) $score->organization_id
            || (int) $validated['semester_id'] !== (int) $score->semester_id) {
            return back()
                ->withErrors(['selection' => 'Organization or semester mismatch for this score.'])
                ->withInput();
        }

        $payload = $this->normalizePayload($validated['payload'] ?? []);
        $scores  = $this->computeScores($payload);

        $score->fill([
            'scored_by'           => $request->user()?->user_id,
            'payload'             => $payload,
            'raw_scores'          => $scores,
            'total_weighted_score' => $scores['total'],
            'scored_at'           => now(),
        ]);
        $score->save();

        return redirect()->route('admin.scoring.index', ['semester_id' => $score->semester_id])
            ->with('success', 'Organization score updated.');
    }

    public function audit(Request $request)
    {
        return $this->buildAuditView($request, 'pages.admin.scoring.audit');
    }

    public function auditPrint(Request $request)
    {
        return $this->buildAuditView($request, 'exports.scoring-audit-print');
    }

    private function buildAuditView(Request $request, string $view)
    {
        $manualFields = [
            'cat1_donation_cash'  => 'Donation – Cash',
            'cat1_donation_kinds' => 'Donation – In Kind',
            'cat6_leadership'     => 'Leadership Training',
        ];

        $semesters = Semester::query()->orderByDesc('starts_at')->get();
        $semesterFilter = $request->integer('semester_id') ?: null;

        $scoresQuery = OrganizationScore::query()->orderByDesc('scored_at');
        if ($semesterFilter) {
            $scoresQuery->where('semester_id', $semesterFilter);
        }
        $scores = $scoresQuery->get();

        $orgNames = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
            ->pluck('name', 'organization_id');

        $semesterNames = $semesters->pluck('name', 'semester_id');

        $scorerNames = collect();
        $scorerIds = $scores->pluck('scored_by')->filter()->unique()->values();
        if ($scorerIds->isNotEmpty()) {
            $scorerNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $scorerIds)
                ->select('u.user_id', DB::raw("CONCAT(p.first_name, ' ', p.last_name) as full_name"))
                ->pluck('full_name', 'user_id');
        }

        $rows = $scores->map(function ($score) use ($orgNames, $semesterNames, $scorerNames, $manualFields) {
            $payload    = (array) ($score->payload ?? []);
            $usedManual = [];
            foreach ($manualFields as $key => $label) {
                if (($payload[$key] ?? 0) > 0) {
                    $usedManual[] = $label;
                }
            }

            return [
                'id'          => $score->organization_score_id,
                'org_name'    => $orgNames[$score->organization_id] ?? 'Unknown',
                'semester'    => $semesterNames[$score->semester_id] ?? '—',
                'total'       => $score->total_weighted_score,
                'scored_at'   => $score->scored_at,
                'scorer_name' => $scorerNames[$score->scored_by] ?? '—',
                'manual_used' => $usedManual,
            ];
        });

        return view($view, [
            'title'          => 'Scoring Audit Log',
            'rows'           => $rows,
            'semesters'      => $semesters,
            'semesterFilter' => $semesterFilter,
            'fieldLabels'    => $manualFields,
        ]);
    }

    public function rankings(Request $request)
    {
        $semesters = Semester::query()->orderByDesc('starts_at')->get();

        $selectedSemesterId = $request->integer('semester_id');
        if (! $selectedSemesterId) {
            $selectedSemesterId = Semester::current()?->semester_id;
        }

        $selectedSemester = $selectedSemesterId
            ? $semesters->firstWhere('semester_id', $selectedSemesterId)
            : null;

        $rows = collect();

        if ($selectedSemesterId) {
            $scores = OrganizationScore::query()
                ->where('semester_id', $selectedSemesterId)
                ->orderByDesc('total_weighted_score')
                ->get();

            $orgIds = $scores->pluck('organization_id')->unique()->filter()->values()->all();
            $orgNames = [];
            if (! empty($orgIds)) {
                $orgNames = DB::table('organizations as o')
                    ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                    ->whereIn('o.organization_id', $orgIds)
                    ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                    ->get()
                    ->pluck('name', 'organization_id')
                    ->all();
            }

            $rows = $scores->map(fn (OrganizationScore $score) => [
                'score'    => $score,
                'org_name' => $orgNames[$score->organization_id] ?? 'Unknown Organization',
                'raw'      => (array) ($score->raw_scores ?? []),
            ]);
        }

        return view('pages.admin.scoring.rankings', [
            'title'              => 'Organization Rankings',
            'semesters'          => $semesters,
            'selectedSemester'   => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'rows'               => $rows,
        ]);
    }

    private function computeAutoInstances(int $organizationId, Semester $semester): array
    {
        $semesterStart = $semester->starts_at;
        $semesterEnd   = $semester->endsAt() ?? now();

        $plans = DB::table('event_plans as ep')
            ->join('requests as r', 'r.request_id', '=', 'ep.request_id')
            ->join('approvals as a', 'a.request', '=', 'r.request_id')
            ->where('ep.organization_id', $organizationId)
            ->where('a.is_rejected', false)
            ->whereNotNull('a.approved_at')
            ->whereBetween('a.approved_at', [$semesterStart, $semesterEnd])
            ->select([
                'ep.event_plan_id', 'ep.event_id', 'ep.activity_types', 'ep.seminar_level',
                'ep.related_to_organization', 'ep.extension_services',
                'ep.sponsor', 'ep.cosponsor_count', 'ep.area_scope',
            ])
            ->get()
            ->map(fn ($p) => [
                'event_plan_id'          => (int) $p->event_plan_id,
                'activity_types'         => $p->activity_types ? json_decode($p->activity_types, true) : [],
                'seminar_level'          => $p->seminar_level,
                'related_to_organization'=> (bool) $p->related_to_organization,
                'extension_services'     => (bool) $p->extension_services,
                'sponsor'                => $p->sponsor,
                'cosponsor_count'        => (int) ($p->cosponsor_count ?? 0),
                'area_scope'             => $p->area_scope,
            ]);

        $arForm = Form::query()->where('route_name', 'accomplishment-report')->first();
        $membersMap         = [];
        $arMomTotal         = 0;
        $hasAnyAr           = false;
        $arRewardedRows     = collect();
        $c2_ssc_meeting_rep   = 0;
        $c2_ssc_meeting_proxy = 0;

        if ($arForm) {
            $arRows = DB::table('form_submissions as fs')
                ->where('fs.form_id', (int) $arForm->getKey())
                ->where('fs.organization_id', $organizationId)
                ->whereBetween('fs.submitted_at', [$semesterStart, $semesterEnd])
                ->select(['fs.event_id', 'fs.payload'])
                ->get();

            foreach ($arRows as $ar) {
                $p = is_string($ar->payload) ? json_decode($ar->payload, true) : (array) $ar->payload;
                if ($ar->event_id) {
                    $membersMap[(int) $ar->event_id] = (int) ($p['members_attended'] ?? 0);
                }
                $arMomTotal += (int) ($p['minutes_of_meeting'] ?? 0);
                $hasAnyAr = true;
                $arRewardedRows->push($p);

                $isMeeting    = ($p['activity_type'] ?? '') === 'Meeting';
                $isSscSponsor = ! empty($p['is_sponsor_ssc']);
                $rop          = $p['rep_or_proxy'] ?? null;
                if ($isMeeting && $isSscSponsor && $rop === 'representative') {
                    $c2_ssc_meeting_rep++;
                }
                if ($isMeeting && $isSscSponsor && $rop === 'proxy') {
                    $c2_ssc_meeting_proxy++;
                }
            }
        }

        $getMembers = fn (array $plan): int => ($membersMap[(int) ($plan['event_id'] ?? 0)] ?? 0);

        // --- Income Generated (auto from approved financial reports) ---
        $c1_income = 0;
        $frForm = Form::query()->where('route_name', 'financial-report')->first();
        if ($frForm) {
            $frFormId = (int) $frForm->getKey();
            $frSubmissions = DB::table('form_submissions as fs')
                ->where('fs.form_id', $frFormId)
                ->where('fs.organization_id', $organizationId)
                ->whereBetween('fs.submitted_at', [$semesterStart, $semesterEnd])
                ->select(['fs.form_submission_id', 'fs.payload'])
                ->get();

            if ($frSubmissions->isNotEmpty()) {
                $submissionIds = $frSubmissions->pluck('form_submission_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $approvedSubIds = DB::table('requests as r')
                    ->join('approvals as a', 'a.request', '=', 'r.request_id')
                    ->where('r.action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
                    ->where('r.organization_id', $organizationId)
                    ->where('a.is_rejected', false)
                    ->whereNotNull('a.approved_at')
                    ->get(['r.payload'])
                    ->map(fn ($row) => (int) ((is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload)['submission_id'] ?? 0))
                    ->filter(fn ($id) => in_array($id, $submissionIds, true))
                    ->all();

                foreach ($frSubmissions as $sub) {
                    if (! in_array((int) $sub->form_submission_id, $approvedSubIds, true)) {
                        continue;
                    }
                    $p = is_string($sub->payload) ? json_decode($sub->payload, true) : (array) $sub->payload;
                    $c1_income += (int) floor((float) ($p['cashOnHand'] ?? 0) / 500);
                }
            }
        }

        // --- Category I ---
        $c1_seminar_college = 0;
        $c1_seminar_univ    = 0;
        $c1_related         = 0;
        $c1_not_related     = 0;
        $c1_cosponsor_pts   = 0;

        foreach ($plans as $plan) {
            $types        = $plan['activity_types'];
            $isSeminar    = in_array('Seminar', $types, true);
            $isPrep       = in_array('Preparation', $types, true);
            $members      = $getMembers($plan);
            $relatedToOrg = $plan['related_to_organization'];

            if ($isSeminar && $plan['seminar_level'] === 'College' && $members > 15) {
                $c1_seminar_college++;
            }
            if ($isSeminar && $plan['seminar_level'] === 'University' && $members > 30) {
                $c1_seminar_univ++;
            }
            if (! $isSeminar && ! $isPrep && $relatedToOrg && $members > 15) {
                $c1_related++;
            }
            if (! $isSeminar && ! $isPrep && ! $relatedToOrg && $members > 15) {
                $c1_not_related++;
            }
            if ($plan['sponsor'] === 'co-sponsors' && ! $relatedToOrg && $plan['cosponsor_count'] >= 2) {
                $c1_cosponsor_pts += (int) floor(10 / $plan['cosponsor_count']);
            }
        }

        // --- Category II ---
        $c2_other_orgs = 0;
        $c2_rep        = ['Local' => 0, 'Provincial' => 0, 'Regional' => 0, 'National' => 0, 'International' => 0];
        $c2_ssc_osa    = 0;
        $c2_ssc_sem    = 0;
        $c2_other_sem  = 0;
        $c2_osa_sem    = 0;
        $c2_help_ssc   = 0;
        $c2_help_other = 0;

        foreach ($plans as $plan) {
            $types        = $plan['activity_types'];
            $isSeminar    = in_array('Seminar', $types, true);
            $isConference = in_array('Conference', $types, true);
            $isPrep       = in_array('Preparation', $types, true);
            $sponsor      = $plan['sponsor'];
            $scope        = $plan['area_scope'];

            if (! $isSeminar && ! $isPrep && $sponsor === 'others') {
                $c2_other_orgs++;
            }
            if (array_key_exists($scope, $c2_rep)) {
                $c2_rep[$scope]++;
            }
            if ($sponsor === 'SSC') {
                $c2_ssc_osa++;
                if ($isSeminar) {
                    $c2_ssc_sem++;
                }
            }
            if (($isSeminar || $isConference) && $sponsor === 'others') {
                $c2_other_sem++;
            }
            if ($isSeminar && $sponsor === 'Admin') {
                $c2_osa_sem++;
            }
            if ($isPrep) {
                if ($sponsor === 'SSC' || $sponsor === 'Admin') {
                    $c2_help_ssc++;
                } elseif ($sponsor === 'others') {
                    $c2_help_other++;
                }
            }
        }

        // --- Category III (from approved ARs with rewards) ---
        $c3 = [
            'group'      => ['Local' => 0, 'Provincial' => 0, 'Regional' => 0, 'National' => 0, 'International' => 0],
            'individual' => ['Local' => 0, 'Provincial' => 0, 'Regional' => 0, 'National' => 0, 'International' => 0],
        ];

        foreach ($arRewardedRows as $p) {
            if (empty($p['has_rewards'])) {
                continue;
            }
            $scope = $p['area_scope_of_award'] ?? '';
            $type  = ($p['is_individual'] ?? '') === 'yes' ? 'individual' : 'group';
            if (isset($c3[$type][$scope])) {
                $c3[$type][$scope]++;
            }
        }

        // --- Category IV ---
        $c4_extension = 0;
        foreach ($plans as $plan) {
            if ($plan['extension_services'] && $getMembers($plan) > 10) {
                $c4_extension++;
            }
        }

        // --- Category V ---
        $c5_tangible = $this->countApprovedProjects($organizationId, $semester);

        // --- Category VI ---
        $c6_documents    = $hasAnyAr ? 1 : 0;
        $c6_meetings     = $arMomTotal > 30 ? 1 : 0;
        $c6_transparency = $c6_documents;

        return [
            'cat1_seminar_college'         => $c1_seminar_college,
            'cat1_seminar_univ'            => $c1_seminar_univ,
            'cat1_activities_related'      => $c1_related,
            'cat1_activities_not_related'  => $c1_not_related,
            'cat1_donation_cash'           => 0,
            'cat1_donation_kinds'          => 0,
            'cat1_cosponsor_pts'           => $c1_cosponsor_pts,
            'cat1_income'                  => $c1_income,
            'cat2_other_orgs'              => $c2_other_orgs,
            'cat2_rep_local'               => $c2_rep['Local'],
            'cat2_rep_provincial'          => $c2_rep['Provincial'],
            'cat2_rep_regional'            => $c2_rep['Regional'],
            'cat2_rep_national'            => $c2_rep['National'],
            'cat2_rep_international'       => $c2_rep['International'],
            'cat2_ssc_osa_activities'      => $c2_ssc_osa,
            'cat2_ssc_seminars'            => $c2_ssc_sem,
            'cat2_other_seminars'          => $c2_other_sem,
            'cat2_osa_seminars'            => $c2_osa_sem,
            'cat2_ssc_meeting_rep'         => $c2_ssc_meeting_rep,
            'cat2_ssc_meeting_proxy'       => $c2_ssc_meeting_proxy,
            'cat2_help_ssc_osa'            => $c2_help_ssc,
            'cat2_help_others'             => $c2_help_other,
            'cat3_group_intl'              => $c3['group']['International'],
            'cat3_group_national'          => $c3['group']['National'],
            'cat3_group_regional'          => $c3['group']['Regional'],
            'cat3_group_provincial'        => $c3['group']['Provincial'],
            'cat3_group_local'             => $c3['group']['Local'],
            'cat3_individual_intl'         => $c3['individual']['International'],
            'cat3_individual_national'     => $c3['individual']['National'],
            'cat3_individual_regional'     => $c3['individual']['Regional'],
            'cat3_individual_provincial'   => $c3['individual']['Provincial'],
            'cat3_individual_local'        => $c3['individual']['Local'],
            'cat4_extension_groups'        => $c4_extension,
            'cat5_tangible_projects'       => $c5_tangible,
            'cat6_documents'               => $c6_documents,
            'cat6_meetings'                => $c6_meetings,
            'cat6_leadership'              => 0,
            'cat6_transparency'            => $c6_transparency,
        ];
    }

    private function countApprovedProjects(int $organizationId, Semester $semester): int
    {
        $formId = Form::query()->where('route_name', 'project-request')->value('id');
        if (! $formId) {
            return 0;
        }

        $end = $semester->endsAt() ?? now();

        return DB::table('requests as r')
            ->join('approvals as a', 'a.request', '=', 'r.request_id')
            ->where('r.organization_id', $organizationId)
            ->where('r.action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('r.payload->form_id', $formId)
            ->where('a.is_rejected', false)
            ->whereNotNull('a.approved_at')
            ->whereBetween('a.approved_at', [$semester->starts_at, $end])
            ->count();
    }

    private function computeScores(array $payload): array
    {
        $get = fn (string $key): int => max(0, (int) ($payload[$key] ?? 0));

        $cat1 = min(100,
            $get('cat1_seminar_college') * 10 +
            $get('cat1_seminar_univ') * 15 +
            $get('cat1_activities_related') * 10 +
            $get('cat1_activities_not_related') * 7 +
            $get('cat1_donation_cash') * 2 +
            $get('cat1_donation_kinds') * 10 +
            $get('cat1_cosponsor_pts') +
            $get('cat1_income')
        );

        $cat2 = min(100,
            $get('cat2_other_orgs') * 10 +
            $get('cat2_rep_local') * 2 +
            $get('cat2_rep_provincial') * 3 +
            $get('cat2_rep_regional') * 5 +
            $get('cat2_rep_national') * 7 +
            $get('cat2_rep_international') * 10 +
            $get('cat2_ssc_osa_activities') * 10 +
            $get('cat2_ssc_seminars') * 10 +
            $get('cat2_other_seminars') * 7 +
            $get('cat2_osa_seminars') * 5 +
            $get('cat2_ssc_meeting_rep') * 2 +
            $get('cat2_ssc_meeting_proxy') * 1 +
            $get('cat2_help_ssc_osa') * 5 +
            $get('cat2_help_others') * 3
        );

        $cat3 = min(50,
            $get('cat3_group_intl') * 20 +
            $get('cat3_group_national') * 15 +
            $get('cat3_group_regional') * 10 +
            $get('cat3_group_provincial') * 7 +
            $get('cat3_group_local') * 5 +
            $get('cat3_individual_intl') * 15 +
            $get('cat3_individual_national') * 10 +
            $get('cat3_individual_regional') * 7 +
            $get('cat3_individual_provincial') * 5 +
            $get('cat3_individual_local') * 3
        );

        $cat4 = min(100, $get('cat4_extension_groups') * 10);

        $cat5 = min(100, $get('cat5_tangible_projects') * 100);

        $cat6 = min(100,
            $get('cat6_documents') * 50 +
            $get('cat6_meetings') * 25 +
            $get('cat6_leadership') * 15 +
            $get('cat6_transparency') * 10
        );

        $total = $cat1 + $cat2 + $cat3 + $cat4 + $cat5 + $cat6;

        return compact('cat1', 'cat2', 'cat3', 'cat4', 'cat5', 'cat6', 'total');
    }

    private function normalizePayload(array $payload): array
    {
        $keys = [
            'cat1_seminar_college', 'cat1_seminar_univ', 'cat1_activities_related',
            'cat1_activities_not_related', 'cat1_donation_cash', 'cat1_donation_kinds',
            'cat1_cosponsor_pts', 'cat1_income',
            'cat2_other_orgs', 'cat2_rep_local', 'cat2_rep_provincial', 'cat2_rep_regional',
            'cat2_rep_national', 'cat2_rep_international', 'cat2_ssc_osa_activities',
            'cat2_ssc_seminars', 'cat2_other_seminars', 'cat2_osa_seminars',
            'cat2_ssc_meeting_rep', 'cat2_ssc_meeting_proxy', 'cat2_help_ssc_osa', 'cat2_help_others',
            'cat3_group_intl', 'cat3_group_national', 'cat3_group_regional',
            'cat3_group_provincial', 'cat3_group_local',
            'cat3_individual_intl', 'cat3_individual_national', 'cat3_individual_regional',
            'cat3_individual_provincial', 'cat3_individual_local',
            'cat4_extension_groups',
            'cat5_tangible_projects',
            'cat6_documents', 'cat6_meetings', 'cat6_leadership', 'cat6_transparency',
        ];

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[$key] = max(0, (int) ($payload[$key] ?? 0));
        }

        return $normalized;
    }

    private function validatePayload(Request $request): array
    {
        $rules = [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'semester_id'     => ['required', 'integer', Rule::exists('semesters', 'semester_id')],
            'payload'         => ['nullable', 'array'],
        ];

        $intFields = [
            'cat1_seminar_college', 'cat1_seminar_univ', 'cat1_activities_related',
            'cat1_activities_not_related', 'cat1_donation_cash', 'cat1_donation_kinds',
            'cat1_cosponsor_pts', 'cat1_income',
            'cat2_other_orgs', 'cat2_rep_local', 'cat2_rep_provincial', 'cat2_rep_regional',
            'cat2_rep_national', 'cat2_rep_international', 'cat2_ssc_osa_activities',
            'cat2_ssc_seminars', 'cat2_other_seminars', 'cat2_osa_seminars',
            'cat2_ssc_meeting_rep', 'cat2_ssc_meeting_proxy', 'cat2_help_ssc_osa', 'cat2_help_others',
            'cat3_group_intl', 'cat3_group_national', 'cat3_group_regional',
            'cat3_group_provincial', 'cat3_group_local',
            'cat3_individual_intl', 'cat3_individual_national', 'cat3_individual_regional',
            'cat3_individual_provincial', 'cat3_individual_local',
            'cat4_extension_groups',
            'cat5_tangible_projects',
            'cat6_documents', 'cat6_meetings', 'cat6_leadership', 'cat6_transparency',
        ];

        foreach ($intFields as $field) {
            $rules["payload.{$field}"] = ['nullable', 'integer', 'min:0'];
        }

        return $request->validate($rules);
    }
}
