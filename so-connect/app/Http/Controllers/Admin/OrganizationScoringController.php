<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Exports\ScoringAuditExport;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationScore;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

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

            $rows = $organizations->map(function ($organization) use ($scores, $selectedSemester) {
                $orgId = (int) $organization->organization_id;
                $score = $scores->get($orgId);

                // A saved score means the org is verified: its weighted total is
                // locked. Otherwise show the live partial score currently tallied
                // from the block triggers (Scoring Rules editor) for this semester.
                $partial = $score
                    ? (float) $score->total_weighted_score
                    : (float) $this->computeScores($this->computeAutoInstances($orgId, $selectedSemester))['total'];

                return [
                    'organization_id'   => $orgId,
                    'organization_name' => $organization->name,
                    'organization_type' => (int) $organization->organization_type,
                    'score'             => $score,
                    'partial_score'     => $partial,
                    'verified'          => (bool) $score,
                ];
            });

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
            'title'            => 'Verify Organization Score',
            'organization'     => $organization,
            'organizationName' => $organizationName,
            'semester'         => $semester,
            'payload'          => $this->computeAutoInstances($organizationId, $semester),
            'score'            => null,
            'isEdit'           => false,
            'readOnly'         => false,
            'customCriteria'   => \App\Services\Scoring\ScoringCatalog::customByCategory(),
            'categoryMeta'     => \App\Services\Scoring\ScoringCatalog::categories(),
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

        $score = OrganizationScore::query()->create([
            'organization_id'     => $organizationId,
            'semester_id'         => $semesterId,
            'scored_by'           => $request->user()?->user_id,
            'payload'             => $payload,
            'raw_scores'          => $scores,
            'total_weighted_score' => $scores['total'],
            'scored_at'           => now(),
        ]);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_SCORING,
            'score_saved',
            'Saved organization score',
            ['organization_id' => $organizationId, 'semester_id' => $semesterId, 'total' => $scores['total']],
            $score,
        );

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
            'title'            => 'Review Organization Score',
            'organization'     => $organization,
            'organizationName' => $organizationName,
            'semester'         => $semester,
            'payload'          => $mergedPayload,
            'score'            => $score,
            'isEdit'           => true,
            // A saved score is a verified score: lock the form to read-only review.
            'readOnly'         => true,
            'customCriteria'   => \App\Services\Scoring\ScoringCatalog::customByCategory(),
            'categoryMeta'     => \App\Services\Scoring\ScoringCatalog::categories(),
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

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_SCORING,
            'score_updated',
            'Updated organization score',
            ['organization_id' => (int) $score->organization_id, 'semester_id' => (int) $score->semester_id, 'total' => $scores['total']],
            $score,
        );

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

    public function auditXlsx(Request $request)
    {
        $data = $this->buildAuditData($request);

        return Excel::download(new ScoringAuditExport($data['rows']), 'scoring-audit-export.xlsx');
    }

    private function buildAuditView(Request $request, string $view)
    {
        $data = $this->buildAuditData($request);

        return view($view, [
            'title'          => 'Scoring Audit Log',
            'rows'           => $data['rows'],
            'semesters'      => $data['semesters'],
            'semesterFilter' => $data['semesterFilter'],
            'fieldLabels'    => $data['manualFields'],
        ]);
    }

    private function buildAuditData(Request $request): array
    {
        $manualFields = \App\Services\Scoring\ScoringCatalog::labels();

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

        $semesterMap = $semesters->keyBy('semester_id');

        $rows = $scores->map(function ($score) use ($orgNames, $semesterNames, $scorerNames, $manualFields, $semesterMap) {
            $payload    = (array) ($score->payload ?? []);
            $semester   = $semesterMap->get($score->semester_id);
            $auto       = $semester ? $this->computeAutoInstances((int) $score->organization_id, $semester) : [];
            $usedManual = [];
            foreach ($manualFields as $key => $label) {
                $payloadValue = (int) ($payload[$key] ?? 0);
                $autoValue = (int) ($auto[$key] ?? 0);
                if ($payloadValue !== $autoValue) {
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

        return compact('rows', 'semesters', 'semesterFilter', 'manualFields');
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
        return app(\App\Services\Scoring\ScoreCalculator::class)->autoInstances($organizationId, $semester);
    }

    private function computeScores(array $payload): array
    {
        return app(\App\Services\Scoring\ScoreCalculator::class)->scores($payload);
    }

    private function normalizePayload(array $payload): array
    {
        $keys = \App\Services\Scoring\ScoringCatalog::keys();

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

        $intFields = \App\Services\Scoring\ScoringCatalog::keys();

        foreach ($intFields as $field) {
            $rules["payload.{$field}"] = ['nullable', 'integer', 'min:0'];
        }

        return $request->validate($rules);
    }
}
