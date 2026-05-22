<?php

namespace App\Http\Controllers;

use App\Models\AccomplishmentMedia;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccomplishmentReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $user->user_type === 2) {
            abort(403);
        }

        $profileRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', $userId)
            ->select(['p.first_name', 'p.middle_name', 'p.last_name'])
            ->first();

        $presidentName = $profileRow ? trim(implode(' ', array_filter([
            $profileRow->first_name,
            $profileRow->middle_name,
            $profileRow->last_name,
        ]))) : '';

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $organizations = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $officerOrgIds)
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
            ->orderBy('od.name')
            ->get();

        $orgIds = $organizations->pluck('organization_id')->toArray();

        $form = Form::query()->where('route_name', 'accomplishment-report')->first();
        $usedEventPlanIds = $form
            ? DB::table('form_submissions')
                ->where('form_id', (int) $form->getKey())
                ->whereNotNull('event_id')
                ->pluck('event_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $rawEvents = DB::table('event_plans as ep')
            ->whereIn('ep.organization_id', $orgIds)
            ->where('ep.status', 'approved')
            ->when(! empty($usedEventPlanIds), fn ($q) => $q->whereNotIn('ep.event_plan_id', $usedEventPlanIds))
            ->orderByDesc('ep.target_date')
            ->select([
                DB::raw('ep.event_plan_id as event_id'),
                'ep.organization_id as org_id',
                'ep.title',
                'ep.target_date as date',
                'ep.activity_types',
                'ep.seminar_level',
                'ep.persons_responsible',
            ])
            ->get();

        $allPersonIds = $rawEvents->flatMap(function ($ev) {
            $ids = $ev->persons_responsible ? json_decode($ev->persons_responsible, true) : [];
            return is_array($ids) ? array_map('intval', $ids) : [];
        })->filter()->unique()->values()->all();

        $personNames = [];
        if (! empty($allPersonIds)) {
            $personNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $allPersonIds)
                ->select([
                    'u.user_id',
                    DB::raw("TRIM(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)) as full_name"),
                ])
                ->get()
                ->pluck('full_name', 'user_id')
                ->all();
        }

        $events = $rawEvents->map(fn ($ev) => [
            'event_id'       => $ev->event_id,
            'org_id'         => $ev->org_id,
            'title'          => $ev->title,
            'date'           => $ev->date,
            'activity_types' => $ev->activity_types ? json_decode($ev->activity_types, true) : [],
            'seminar_level'  => $ev->seminar_level,
            'people'         => collect($ev->persons_responsible ? json_decode($ev->persons_responsible, true) : [])
                ->map(fn ($id) => $personNames[(int) $id] ?? null)
                ->filter()
                ->implode(', '),
        ]);

        return view('pages.form.accomplishment-report', [
            'title'         => 'Accomplishment Report',
            'organizations' => $organizations,
            'events'        => $events,
            'presidentName' => $presidentName,
            'currentSchoolYear' => Semester::currentSchoolYear(),
        ]);
    }

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $user->user_type === 2) {
            abort(403);
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
            'organization'    => ['required', 'string', 'max:255'],
            'schoolYear'      => ['required', 'string', 'max:20'],
            'title'           => ['required', 'string', 'max:255'],
            'date'            => ['required', 'date'],
            'people'          => ['required', 'string'],
            'problem'         => ['nullable', 'string'],
            'photos'          => ['nullable', 'file', 'mimes:jpeg,png,pdf', 'max:5120'],
            'name'            => ['required', 'string', 'max:255'],
            'signature'       => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'adviserRow'      => ['nullable', 'array'],
            'adviserRow.*'    => ['nullable', 'string', 'max:255'],
            'event_id'            => ['nullable', 'integer', 'min:1'],
            'members_attended'    => ['nullable', 'integer', 'min:0'],
            'activity_type'       => ['nullable', 'string', 'in:Seminar,Clean Up Drive,Conference,Workshop,Preparation,Meeting,others'],
            'has_rewards'         => ['nullable', 'boolean'],
            'is_individual'       => ['required_if:has_rewards,1', 'in:yes,no'],
            'area_scope_of_award' => ['exclude_unless:has_rewards,1', 'required', 'in:Local,Provincial,Regional,National,International'],
            'minutes_of_meeting'  => ['nullable', 'integer', 'min:0'],
            'summary_of_expenses' => ['nullable', 'numeric', 'min:0'],
            'is_sponsor_ssc'      => ['nullable', 'boolean'],
            'rep_or_proxy'        => ['nullable', 'string', 'in:representative,proxy', 'required_if:activity_type,Meeting'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        if (! in_array($organizationId, $officerOrgIds, true)) {
            abort(403);
        }

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $payload = [
            'organization' => $validated['organization'],
            'schoolYear'   => $validated['schoolYear'],
            'title'        => $validated['title'],
            'date'         => $validated['date'],
            'people'       => $validated['people'],
            'problem'      => $validated['problem'] ?? '',
            'name'         => $validated['name'],
            'adviserName'  => collect($validated['adviserRow'] ?? [])->filter()->values()->isNotEmpty()
                ? '(' . collect($validated['adviserRow'])->filter()->map(fn($v) => '"' . $v . '"')->implode(', ') . ')'
                : '',
            'activity_type'       => $validated['activity_type'] ?? null,
            'members_attended'    => isset($validated['members_attended']) ? (int) $validated['members_attended'] : null,
            'has_rewards'         => (bool) ($validated['has_rewards'] ?? false),
            'is_individual'       => $validated['is_individual'] ?? null,
            'area_scope_of_award' => $validated['area_scope_of_award'] ?? null,
            'minutes_of_meeting'  => isset($validated['minutes_of_meeting']) ? (int) $validated['minutes_of_meeting'] : null,
            'summary_of_expenses' => isset($validated['summary_of_expenses']) ? (float) $validated['summary_of_expenses'] : null,
            'is_sponsor_ssc'      => (bool) ($validated['is_sponsor_ssc'] ?? false),
            'rep_or_proxy'        => $validated['rep_or_proxy'] ?? null,
        ];

        $photoCopyPath = null;

        foreach (['photos', 'signature'] as $fileField) {
            if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                $file = $request->file($fileField);
                $path = $file->storeAs(
                    $sigDir,
                    Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
                $payload[$fileField] = $path;

                // Copy the photos file to the accomplishment media library (images only, not PDFs).
                if ($fileField === 'photos' && str_starts_with($file->getMimeType(), 'image/')) {
                    $copyDir = 'posts/media/accomplishment/'.now()->format('Y/m');
                    $photoCopyPath = $file->storeAs(
                        $copyDir,
                        Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                        'public'
                    );
                }
            } else {
                $payload[$fileField] = '';
            }
        }

        $form = Form::query()->where('route_name', 'accomplishment-report')->firstOrFail();

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'event_id'        => isset($validated['event_id']) ? (int) $validated['event_id'] : null,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        if ($photoCopyPath) {
            AccomplishmentMedia::create([
                'form_submission_id' => $submission->getKey(),
                'organization_id'    => $organizationId,
                'file_path'          => $photoCopyPath,
                'activity_title'     => $validated['title'],
                'submitted_at'       => now(),
            ]);
        }

        $documentGenerationService->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()->route('accomplishment-report')
            ->with('success', 'Accomplishment report submitted and is pending admin review.');
    }
}
