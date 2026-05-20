<?php

namespace App\Http\Controllers\Admin;

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

        $rows = collect();

        if ($selectedSemesterId) {
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->orderBy('name')
                ->get();

            $scores = OrganizationScore::query()
                ->where('semester_id', $selectedSemesterId)
                ->get()
                ->keyBy('organization_id');

            $rows = $organizations->map(function ($organization) use ($scores) {
                return [
                    'organization_id' => (int) $organization->organization_id,
                    'organization_name' => $organization->name,
                    'score' => $scores->get($organization->organization_id),
                ];
            });
        }

        return view('pages.admin.scoring.index', [
            'title' => 'Organization Scoring',
            'semesters' => $semesters,
            'selectedSemester' => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'rows' => $rows,
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

        $organization = Organization::query()->with('detail')->findOrFail($organizationId);
        $semester = Semester::query()->findOrFail($semesterId);

        return view('pages.admin.scoring.create', [
            'title' => 'Score Organization',
            'organization' => $organization,
            'organizationName' => $organization->detail?->name ?? 'Unknown Organization',
            'semester' => $semester,
            'payload' => [],
            'hasApprovedProjects' => $this->hasApprovedProjects($organizationId, $semester),
            'score' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $organizationId = (int) $validated['organization_id'];
        $semesterId = (int) $validated['semester_id'];

        $existing = OrganizationScore::query()
            ->where('organization_id', $organizationId)
            ->where('semester_id', $semesterId)
            ->first();

        if ($existing) {
            return redirect()->route('admin.scoring.edit', $existing->organization_score_id);
        }

        $organization = Organization::query()->with('detail')->findOrFail($organizationId);
        $semester = Semester::query()->findOrFail($semesterId);

        $payload = $this->normalizePayload($validated['payload'] ?? []);
        $scores = $this->computeScores($payload, $this->hasApprovedProjects($organizationId, $semester));

        OrganizationScore::query()->create([
            'organization_id' => $organizationId,
            'semester_id' => $semesterId,
            'scored_by' => $request->user()?->user_id,
            'payload' => $payload,
            'raw_scores' => $scores,
            'total_weighted_score' => $scores['total_weighted'],
            'scored_at' => now(),
        ]);

        return redirect()->route('admin.scoring.index', ['semester_id' => $semesterId])
            ->with('success', 'Organization score saved.');
    }

    public function edit(int $id)
    {
        $score = OrganizationScore::query()->findOrFail($id);
        $organization = Organization::query()->with('detail')->findOrFail($score->organization_id);
        $semester = Semester::query()->findOrFail($score->semester_id);

        return view('pages.admin.scoring.create', [
            'title' => 'Edit Organization Score',
            'organization' => $organization,
            'organizationName' => $organization->detail?->name ?? 'Unknown Organization',
            'semester' => $semester,
            'payload' => (array) ($score->payload ?? []),
            'hasApprovedProjects' => $this->hasApprovedProjects($organization->organization_id, $semester),
            'score' => $score,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $score = OrganizationScore::query()->findOrFail($id);
        $validated = $this->validatePayload($request);

        if ((int) $validated['organization_id'] !== (int) $score->organization_id
            || (int) $validated['semester_id'] !== (int) $score->semester_id) {
            return back()
                ->withErrors(['selection' => 'Organization or semester mismatch for this score.'])
                ->withInput();
        }

        $semester = Semester::query()->findOrFail($score->semester_id);

        $payload = $this->normalizePayload($validated['payload'] ?? []);
        $scores = $this->computeScores($payload, $this->hasApprovedProjects($score->organization_id, $semester));

        $score->fill([
            'scored_by' => $request->user()?->user_id,
            'payload' => $payload,
            'raw_scores' => $scores,
            'total_weighted_score' => $scores['total_weighted'],
            'scored_at' => now(),
        ]);
        $score->save();

        return redirect()->route('admin.scoring.index', ['semester_id' => $score->semester_id])
            ->with('success', 'Organization score updated.');
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

            $rows = $scores->map(function (OrganizationScore $score) use ($orgNames) {
                return [
                    'score' => $score,
                    'org_name' => $orgNames[$score->organization_id] ?? 'Unknown Organization',
                    'raw' => (array) ($score->raw_scores ?? []),
                ];
            });
        }

        return view('pages.admin.scoring.rankings', [
            'title' => 'Organization Rankings',
            'semesters' => $semesters,
            'selectedSemester' => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'rows' => $rows,
        ]);
    }

    private function hasApprovedProjects(int $organizationId, Semester $semester): bool
    {
        $formId = Form::query()->where('route_name', 'project-request')->value('id');
        if (! $formId) {
            return false;
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
            ->exists();
    }

    private function normalizePayload(array $payload): array
    {
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

        $merged = array_merge($defaults, $payload);

        foreach (['ss_cosponsor', 'ap_ssc_osa_activities', 'ap_ssc_seminars', 'ap_other_seminars', 'ap_osa_seminars', 'tangible_auto'] as $field) {
            $merged[$field] = ! empty($merged[$field]) ? 1 : 0;
        }

        return $merged;
    }

    private function computeScores(array $payload, bool $hasApprovedProjects): array
    {
        $getInt = fn (string $key): int => (int) ($payload[$key] ?? 0);
        $getBool = fn (string $key): bool => ! empty($payload[$key]);

        $cosponsorPts = 0;
        if ($getBool('ss_cosponsor')) {
            $count = max(2, (int) ($payload['ss_cosponsor_count'] ?? 2));
            $cosponsorPts = (int) floor(10 / $count);
        }

        $sole = min(100,
            $getInt('ss_seminar_college') * 10 +
            $getInt('ss_seminar_univ') * 15 +
            $getInt('ss_activities_related') * 10 +
            $getInt('ss_activities_not_related') * 7 +
            $getInt('ss_donation_cash') * 2 +
            $getInt('ss_donation_kinds') +
            $cosponsorPts +
            (int) floor($getInt('ss_income') / 500)
        );

        $active = min(100,
            $getInt('ap_other_orgs_pts') +
            $getInt('ap_rep_level') +
            ($getBool('ap_ssc_osa_activities') ? 10 : 0) +
            ($getBool('ap_ssc_seminars') ? 10 : 0) +
            ($getBool('ap_other_seminars') ? 7 : 0) +
            ($getBool('ap_osa_seminars') ? 5 : 0) +
            $getInt('ap_ssc_meeting') +
            $getInt('ap_ssc_help')
        );

        $awards = min(50,
            $getInt('aw_group_level') +
            $getInt('aw_individual_level')
        );

        $extension = min(100, $getInt('es_groups') * 10);

        $tangible = $hasApprovedProjects ? 100 : 0;

        $admin =
            $getInt('adm_documents') +
            $getInt('adm_meetings') +
            $getInt('adm_leadership') +
            $getInt('adm_transparency');

        $weighted = round(
            ($sole / 100 * 20) +
            ($active / 100 * 20) +
            ($awards / 50 * 5) +
            ($extension / 100 * 15) +
            ($tangible / 100 * 20) +
            ($admin / 100 * 20),
            2
        );

        return [
            'sole' => $sole,
            'active' => $active,
            'awards' => $awards,
            'extension' => $extension,
            'tangible' => $tangible,
            'admin' => $admin,
            'weighted' => $weighted,
            'total_weighted' => $weighted,
        ];
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'semester_id')],
            'payload' => ['nullable', 'array'],
            'payload.ss_seminar_college' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_seminar_univ' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_activities_related' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_activities_not_related' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_donation_cash' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_donation_kinds' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ss_cosponsor' => ['nullable', 'boolean'],
            'payload.ss_cosponsor_count' => ['nullable', 'integer', 'min:2', 'max:10'],
            'payload.ss_income' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'payload.ap_other_orgs_pts' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ap_rep_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.ap_ssc_osa_activities' => ['nullable', 'boolean'],
            'payload.ap_ssc_seminars' => ['nullable', 'boolean'],
            'payload.ap_other_seminars' => ['nullable', 'boolean'],
            'payload.ap_osa_seminars' => ['nullable', 'boolean'],
            'payload.ap_ssc_meeting' => ['nullable', 'integer', 'min:0', 'max:2'],
            'payload.ap_ssc_help' => ['nullable', 'integer', 'min:0', 'max:5'],
            'payload.aw_group_level' => ['nullable', 'integer', 'min:0', 'max:20'],
            'payload.aw_individual_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.es_groups' => ['nullable', 'integer', 'min:0', 'max:10'],
            'payload.tangible_auto' => ['nullable', 'boolean'],
            'payload.adm_documents' => ['nullable', 'integer', 'min:0', 'max:50'],
            'payload.adm_meetings' => ['nullable', 'integer', 'min:0', 'max:25'],
            'payload.adm_leadership' => ['nullable', 'integer', 'min:0', 'max:15'],
            'payload.adm_transparency' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
    }
}
