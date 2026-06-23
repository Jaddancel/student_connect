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

class OrganizationAccreditationWizardController extends Controller
{
    private const SESSION_WORKPLAN = 'accred_wizard_workplan_id';
    private const SESSION_SIG = 'accred_wizard_sig_path';

    public function showStep1(Request $request)
    {
        $data = $this->loadOrganizationData($request);

        return view('pages.form.accreditation-wizard.step1-workplan', $data);
    }

    public function saveStep1(Request $request)
    {
        $request->validate([
            'workplan_id' => ['required', 'integer'],
        ]);

        $request->session()->put(self::SESSION_WORKPLAN, (int) $request->input('workplan_id'));

        return redirect()->route('accreditation.wizard.step2');
    }

    public function showStep2(Request $request)
    {
        abort_if(! $request->session()->has(self::SESSION_WORKPLAN), 404, 'Please complete step 1 first.');

        return view('pages.form.accreditation-wizard.step2-signature');
    }

    public function saveStep2(Request $request)
    {
        abort_if(! $request->session()->has(self::SESSION_WORKPLAN), 404, 'Please complete step 1 first.');

        $request->validate([
            'signature_file' => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        // Remove old temp file if present
        $oldPath = $request->session()->get(self::SESSION_SIG);
        if ($oldPath) {
            @unlink(storage_path('app/public/'.$oldPath));
        }

        $file = $request->file('signature_file');
        $sigDir = 'form-signatures/tmp';
        $sigPath = $file->storeAs($sigDir, Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(), 'public');

        $request->session()->put(self::SESSION_SIG, $sigPath);

        return redirect()->route('accreditation.wizard.step3');
    }

    public function showStep3(Request $request)
    {
        abort_if(! $request->session()->has(self::SESSION_WORKPLAN), 404, 'Please complete step 1 first.');
        abort_if(! $request->session()->has(self::SESSION_SIG), 404, 'Please complete step 2 first.');

        $data = $this->loadOrganizationData($request);
        $data['sessionWorkplanId'] = $request->session()->get(self::SESSION_WORKPLAN);
        $data['sessionSigPath'] = $request->session()->get(self::SESSION_SIG);

        return view('pages.form.accreditation-wizard.step3-review', $data);
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
            'sig_path'            => ['nullable', 'string', 'max:500'],
            'signaturePresident'  => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        // Must have either a pre-stored sig or a new file
        $hasSigPath = ! empty($validated['sig_path']);
        $hasSigFile = $request->hasFile('signaturePresident') && $request->file('signaturePresident')->isValid();
        if (! $hasSigPath && ! $hasSigFile) {
            return back()->withErrors(['signaturePresident' => 'A signature is required.'])->withInput();
        }

        $organizationId = (int) ($validated['organization_id'] ?? 0) ?: null;
        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        if ($organizationId !== null && ! in_array($organizationId, $officerOrgIds, true)) {
            abort(403);
        }

        $recognitionType = $validated['recognition_type'] ?? null;
        $c1 = $recognitionType === 'c1';
        $c2 = $recognitionType === 'c2';

        $workplanActivity = [];
        $workplanDate = [];
        $workplanResources = [];
        $workplanName = '';

        if (! empty($validated['workplan_id'])) {
            $workplan = Workplan::find((int) $validated['workplan_id']);
            if ($workplan) {
                $workplanName = $workplan->semester?->name ?? '';
                $plans = (new WorkplanService())->getApprovedPlansForWorkplan($workplan);
                foreach ($plans as $plan) {
                    $workplanActivity[] = $plan->title;
                    $workplanDate[] = $plan->target_date->format('M d, Y');
                    $workplanResources[] = $plan->resources_needed;
                }
            }
        }

        // Resolve signature path: prefer new file upload over pre-stored path
        if ($hasSigFile) {
            $file = $request->file('signaturePresident');
            $sigDir = 'form-signatures/'.now()->format('Y/m');
            $sigPath = $file->storeAs($sigDir, Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(), 'public');
        } else {
            // Promote tmp sig to permanent location
            $tmpPath = $validated['sig_path'];
            $permDir = 'form-signatures/'.now()->format('Y/m');
            $filename = Str::lower(Str::random(16)).'.'.pathinfo($tmpPath, PATHINFO_EXTENSION);
            $permPath = $permDir.'/'.$filename;
            \Illuminate\Support\Facades\Storage::disk('public')->copy($tmpPath, $permPath);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tmpPath);
            $sigPath = $permPath;
        }

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
            'workplan'            => $workplanName,
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

        // Clear wizard session state
        $request->session()->forget([self::SESSION_WORKPLAN, self::SESSION_SIG]);

        return redirect()->route('organization-recognition')
            ->with('success', 'Your application has been submitted and is pending admin approval.');
    }

    private function loadOrganizationData(Request $request): array
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $user->user_type === 2) {
            abort(403, 'This form is for organization officers only.');
        }

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

        $organizationId = $organizations->count() === 1 ? (int) $organizations->first()->organization_id : null;
        $orgIds = $organizations->pluck('organization_id')->map(fn ($id) => (int) $id)->toArray();

        $presidentsByOrg = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('oo.organization', $orgIds)
            ->where('oo.role', 'president')
            ->select(['oo.organization', 'p.first_name', 'p.middle_name', 'p.last_name'])
            ->get()
            ->mapWithKeys(function ($row) {
                $name = trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name])));

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

        return [
            'title'               => 'Application for Recognition/Renewal of Student Organization',
            'presidentName'       => $presidentName,
            'presidentsByOrg'     => $presidentsByOrg,
            'organizations'       => $organizations,
            'organizationId'      => $organizationId,
            'finalisedWorkplans'  => $finalisedWorkplans,
            'workplanActivities'  => $workplanActivities,
            'defaultWorkplanId'   => $finalisedWorkplans->first()?->workplan_id,
        ];
    }
}
