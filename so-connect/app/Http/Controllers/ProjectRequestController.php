<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $profileRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', $userId)
            ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
            ->first();

        $presidentName = $profileRow ? trim(implode(' ', array_filter([
            $profileRow->first_name,
            $profileRow->middle_name,
            $profileRow->last_name,
        ]))) : '';

        $presidentContact = $profileRow?->contact_number ?? '';

        if ($isAdmin) {
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        } else {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $officerOrgIds)
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        }

        return view('pages.form.project-request', [
            'title' => 'Letter of Intent / Project Request',
            'isAdmin' => $isAdmin,
            'organizations' => $organizations,
            'presidentName' => $presidentName,
            'presidentContact' => $presidentContact,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $docService)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
            'projectTitle' => ['required', 'string', 'max:255'],
            'natureOfProject' => ['required', 'string', 'max:255'],
            'projectArea' => ['required', 'string', 'max:255'],
            'letterOfIntent' => ['required', 'string', 'max:10000'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $officerOrgIds, true)) {
                abort(403);
            }
        }

        $form = Form::query()->where('route_name', 'project-request')->first();

        if (! $form) {
            return back()->withErrors(['form' => 'Project request form is not configured.']);
        }

        $organizationName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $organizationId)
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
            ->value('organization_name') ?? 'Unknown Organization';

        $payload = [
            'organization_id' => $organizationId,
            'organization' => $organizationName,
            'projectTitle' => $validated['projectTitle'],
            'natureOfProject' => $validated['natureOfProject'],
            'projectArea' => $validated['projectArea'],
            'letterOfIntent' => $validated['letterOfIntent'],
        ];

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by' => $userId,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        $docService->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId
        );

        return redirect()->route('project-request')
            ->with('success', 'Project request submitted. Awaiting admin approval.');
    }
}
