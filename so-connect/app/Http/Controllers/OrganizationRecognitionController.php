<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
use App\Models\Workplan;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use App\Services\WorkplanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationRecognitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $user->user_type === 2) {
            abort(403, 'This form is for organization officers only.');
        }

        $organizations = collect();
        $organizationId = null;

        $organizations = DB::table('organization_officers as oo')
            ->join('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('oo.user', $userId)
            ->whereIn('oo.role', ['officer', 'president'])
            ->select([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->get()
            ->unique('organization_id')
            ->values();

        if ($organizations->count() === 1) {
            $organizationId = (int) $organizations->first()->organization_id;
        }

        $orgIds = $organizations->pluck('organization_id')->map(fn ($id) => (int) $id)->toArray();

        $presidentsByOrg = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('oo.organization', $orgIds)
            ->where('oo.role', 'president')
            ->select(['oo.organization', 'p.first_name', 'p.middle_name', 'p.last_name'])
            ->get()
            ->mapWithKeys(function ($row) {
                $name = trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ])));
                return [(int) $row->organization => $name];
            })
            ->all();

        $firstOrgId = $organizationId ?? (int) ($organizations->first()?->organization_id ?? 0);
        $presidentName = $presidentsByOrg[$firstOrgId] ?? '';

        $activeSemester = Semester::current();

        $finalisedWorkplans = Workplan::query()
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'finalized')
            ->when($activeSemester, fn ($q) => $q->where('semester_id', $activeSemester->semester_id))
            ->with('semester')
            ->orderByDesc('finalized_at')
            ->get();

        $workplanService = new WorkplanService();
        $workplanActivities = [];
        foreach ($finalisedWorkplans as $wp) {
            $plans = $workplanService->getApprovedPlansForWorkplan($wp);
            $workplanActivities[$wp->workplan_id] = $plans->map(fn ($p) => [
                'title'     => $p->title,
                'date'      => $p->target_date->format('M d, Y'),
                'resources' => $p->resources_needed,
            ])->values()->all();
        }

        $defaultWorkplanId = $finalisedWorkplans->first()?->workplan_id;

        return view('pages.form.organization-recognition', [
            'title'               => 'Application for Recognition/Renewal of Student Organization',
            'presidentName'       => $presidentName,
            'presidentsByOrg'     => $presidentsByOrg,
            'organizations'       => $organizations,
            'organizationId'      => $organizationId,
            'finalisedWorkplans'  => $finalisedWorkplans,
            'workplanActivities'  => $workplanActivities,
            'defaultWorkplanId'   => $defaultWorkplanId,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $docService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $user->user_type === 2) {
            abort(403, 'This form is for organization officers only.');
        }

        $validated = $request->validate([
            'organization'        => ['required', 'string', 'max:255'],
            'organization_id'     => ['nullable', 'integer'],
            'recognition_type'    => ['nullable', 'in:c1,c2'],
            'name_of_president'   => ['required', 'string', 'max:255'],
            'nameOfAdviserRow'    => ['required', 'array', 'min:1'],
            'nameOfAdviserRow.*'  => ['required', 'string', 'max:255'],
            'date'                => ['nullable', 'date'],
            'freshman'            => ['nullable', 'integer', 'min:0'],
            'sophomore'           => ['nullable', 'integer', 'min:0'],
            'junior'              => ['nullable', 'integer', 'min:0'],
            'total'               => ['nullable', 'integer', 'min:0'],
            'objectives'          => ['nullable', 'string'],
            'workplan_id'         => ['nullable', 'integer'],
            'nameOfAdviser1'      => ['nullable', 'string', 'max:255'],
            'nameOfAdviser2'      => ['nullable', 'string', 'max:255'],
            'signaturePresident'  => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $organizationId = (int) ($validated['organization_id'] ?? 0) ?: null;

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        if ($organizationId !== null && ! in_array($organizationId, $officerOrgIds, true)) {
            abort(403);
        }

        $recognitionType = $validated['recognition_type'] ?? null;
        $c1 = $recognitionType === 'c1';
        $c2 = $recognitionType === 'c2';

        $workplanActivity  = [];
        $workplanDate      = [];
        $workplanResources = [];
        $workplanName      = '';

        if (! empty($validated['workplan_id'])) {
            $workplan = Workplan::find((int) $validated['workplan_id']);
            if ($workplan) {
                $workplanName = $workplan->semester?->name ?? '';
                $plans = (new WorkplanService())->getApprovedPlansForWorkplan($workplan);
                foreach ($plans as $plan) {
                    $workplanActivity[]  = $plan->title;
                    $workplanDate[]      = $plan->target_date->format('M d, Y');
                    $workplanResources[] = $plan->resources_needed;
                }
            }
        }

        $sigDir  = 'form-signatures/' . now()->format('Y/m');
        $sigPath = '';

        if ($request->hasFile('signaturePresident') && $request->file('signaturePresident')->isValid()) {
            $file    = $request->file('signaturePresident');
            $sigPath = $file->storeAs(
                $sigDir,
                Str::lower(Str::random(16)) . '.' . $file->getClientOriginalExtension(),
                'public'
            );
        }

        $workplanText = $workplanName;

        $payload = [
            'c1'                  => $c1,
            'c2'                  => $c2,
            'nameoforganization'  => $validated['organization'],
            'presidentname'       => $validated['name_of_president'],
            'nameOfAdviserRow'    => array_values(array_filter($validated['nameOfAdviserRow'], fn ($v) => filled($v))),
            'date'                => $validated['date'] ?? '',
            'freshman'            => $validated['freshman'] ?? '',
            'sophomore'           => $validated['sophomore'] ?? '',
            'junior'              => $validated['junior'] ?? '',
            'total'               => $validated['total'] ?? '',
            'objectives'          => $validated['objectives'] ?? '',
            'workplan_id'         => $validated['workplan_id'] ?? '',
            'workplan'            => $workplanText,
            'workplanActivity'    => $workplanActivity,
            'workplanDate'        => $workplanDate,
            'workplanResources'   => $workplanResources,
            'nameOfPresident'     => $validated['name_of_president'],
            'signaturePresident'  => $sigPath,
            'adviserleft'         => $validated['nameOfAdviser1'] ?? '',
            'adviserright'        => $validated['nameOfAdviser2'] ?? '',
            'chair'               => '',
            'signatureChair'      => '',
            'director'            => '',
            'signatureDirector'   => '',
            'create_organization' => $c1,
        ];

        $form = Form::query()->where('route_name', 'organization-recognition')->firstOrFail();

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        $docService->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()->route('organization-recognition')
            ->with('success', 'Your application has been submitted and is pending admin approval.');
    }
}
