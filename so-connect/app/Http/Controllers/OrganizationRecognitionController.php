<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\RequestApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationRecognitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $presidentName = '';
        $organizations = collect();
        $organizationId = null;

        $profileRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', $userId)
            ->select(['p.first_name', 'p.middle_name', 'p.last_name'])
            ->first();

        if ($profileRow) {
            $presidentName = trim(implode(' ', array_filter([
                $profileRow->first_name,
                $profileRow->middle_name,
                $profileRow->last_name,
            ])));
        }

        if (! $isAdmin) {
            $organizations = DB::table('organization_officers as oo')
                ->join('members as m', 'm.member_id', '=', 'oo.member')
                ->join('organizations as o', 'o.organization_id', '=', 'm.organization')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('m.user', $userId)
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
        }

        return view('pages.form.organization-recognition', [
            'title' => 'Application for Recognition/Renewal of Student Organization',
            'isAdmin' => $isAdmin,
            'presidentName' => $presidentName,
            'organizations' => $organizations,
            'organizationId' => $organizationId,
        ]);
    }

    public function store(
        Request $request,
        DocumentGenerationService $docService,
        RequestApprovalService $approvalService,
    ) {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $validated = $request->validate([
            'nameOfOrganization' => ['required', 'string', 'max:255'],
            'presidentName'      => ['required', 'string', 'max:255'],
            'facultyAdvisers'    => ['required', 'string', 'max:255'],
            'recognitionDate'    => ['nullable', 'date'],
            'freshmanNumber'     => ['nullable', 'integer', 'min:0'],
            'sophomoreNumber'    => ['nullable', 'integer', 'min:0'],
            'juniorNumber'       => ['nullable', 'integer', 'min:0'],
            'total'              => ['nullable', 'integer', 'min:0'],
            'objectives'         => ['nullable', 'string'],
            'workplan'           => ['nullable', 'string'],
            'organization_id'    => ['nullable', 'integer'],
            'recognition_type'   => ['nullable', 'in:c1,c2'],
            'adviserLeft'        => ['nullable', 'string', 'max:255'],
            'adviserRight'       => ['nullable', 'string', 'max:255'],
            'chair'              => ['nullable', 'string', 'max:255'],
            'director'           => ['nullable', 'string', 'max:255'],
        ]);

        $form = Form::query()->where('route_name', 'organization-recognition')->firstOrFail();

        $organizationId = $isAdmin ? null : ((int) ($validated['organization_id'] ?? 0) ?: null);

        $recognitionType = $validated['recognition_type'] ?? null;
        $c1 = $recognitionType === 'c1';
        $c2 = $recognitionType === 'c2';

        $payload = [
            'c1'                 => $c1,
            'c2'                 => $c2,
            'nameOfOrganization' => $validated['nameOfOrganization'],
            'presidentName'      => $validated['presidentName'],
            'facultyAdvisers'    => $validated['facultyAdvisers'],
            'recognitionDate'    => $validated['recognitionDate'] ?? '',
            'freshmanNumber'     => $validated['freshmanNumber'] ?? '',
            'sophomoreNumber'    => $validated['sophomoreNumber'] ?? '',
            'juniorNumber'       => $validated['juniorNumber'] ?? '',
            'total'              => $validated['total'] ?? '',
            'objectives'         => $validated['objectives'] ?? '',
            'workplan'           => $validated['workplan'] ?? '',
            'adviserLeft'        => $validated['adviserLeft'] ?? '',
            'adviserRight'       => $validated['adviserRight'] ?? '',
            'chair'              => $isAdmin ? ($validated['chair'] ?? '') : '',
            'director'           => $isAdmin ? ($validated['director'] ?? '') : '',
            'create_organization'=> $c1,
        ];

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        $actionRequest = $docService->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        if ($isAdmin) {
            try {
                $approvalService->approve($actionRequest, $userId);
            } catch (\Throwable) {
                // Document generation may fail if no template is uploaded yet; request still recorded.
            }
        }

        return redirect()->route('organization-recognition')
            ->with('success', $isAdmin
                ? 'Form submitted and auto-approved. The document will be available in Download Files.'
                : 'Your application has been submitted and is pending admin approval.'
            );
    }
}
