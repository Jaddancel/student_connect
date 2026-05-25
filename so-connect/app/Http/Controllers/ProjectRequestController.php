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

        if ($isAdmin) {
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
            $orgIds = $organizations->pluck('organization_id')->map(fn ($id) => (int) $id)->all();
        } else {
            $orgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name")])
                ->orderBy('od.name')
                ->get();
        }

        $presidentsByOrg = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('oo.organization', $orgIds)
            ->where('oo.role', 'president')
            ->select(['oo.organization', 'p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
            ->get()
            ->mapWithKeys(function ($row) {
                $name = trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ])));
                return [(int) $row->organization => [
                    'name'    => $name,
                    'contact' => $row->contact_number ?? '',
                ]];
            })
            ->all();

        $firstOrgId = (int) ($organizations->first()?->organization_id ?? 0);
        $presidentName    = $presidentsByOrg[$firstOrgId]['name']    ?? '';
        $presidentContact = $presidentsByOrg[$firstOrgId]['contact']  ?? '';

        return view('pages.form.project-request', [
            'title'            => 'Project Request',
            'isAdmin'          => $isAdmin,
            'organizations'    => $organizations,
            'presidentName'    => $presidentName,
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
            'organization_id'  => ['required', 'integer', 'min:1'],
            'projectTitle'     => ['required', 'string', 'max:255'],
            'natureOfProject'  => ['required', 'string', 'max:255'],
            'projectArea'      => ['required', 'string', 'max:255'],
            'letterOfIntent'   => ['required', 'string', 'max:10000'],
            'is_donation'      => ['nullable', 'in:0,1'],
            'donation_amount'  => ['nullable', 'numeric', 'min:0'],
            'in_kinds'         => ['nullable', 'array'],
            'in_kinds.*'       => ['nullable', 'string', 'max:255'],
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

        $isDonation = (int) ($validated['is_donation'] ?? 0) === 1;
        $inKinds = $isDonation
            ? array_values(array_filter($validated['in_kinds'] ?? [], fn ($v) => trim((string) $v) !== ''))
            : [];

        $payload = [
            'organization_id' => $organizationId,
            'organization'    => $organizationName,
            'projectTitle'    => $validated['projectTitle'],
            'natureOfProject' => $validated['natureOfProject'],
            'projectArea'     => $validated['projectArea'],
            'letterOfIntent'  => $validated['letterOfIntent'],
            'is_donation'     => $isDonation,
            'donation_amount' => $isDonation ? (float) ($validated['donation_amount'] ?? 0) : null,
            'in_kinds'        => $inKinds,
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
