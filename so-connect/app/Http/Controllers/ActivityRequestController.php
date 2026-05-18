<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

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

        return view('pages.form.activity-request', [
            'title'         => 'Request for Organizational Activity',
            'isAdmin'       => $isAdmin,
            'organizations' => $organizations,
            'presidentName' => $presidentName,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $validated = $request->validate([
            'organization_id'      => ['required', 'integer', 'min:1'],
            'organization'         => ['required', 'string', 'max:255'],
            'date'                              => ['required', 'date'],
            'projectActivity'                   => ['required', 'string', 'max:500'],
            'purposed'                          => ['required', 'string', 'max:1000'],
            'dayOfTheWeek'                      => ['nullable', 'string', 'max:20'],
            'time'                              => ['required', 'string', 'max:50'],
            'placeAndVenue'                     => ['required', 'string', 'max:255'],
            'facilitiesOrEquipmentToBeUsedRow'  => ['nullable', 'array', 'max:10'],
            'facilitiesOrEquipmentToBeUsedRow.*'=> ['nullable', 'string', 'max:255'],
            'presidentName'                     => ['required', 'string', 'max:255'],
            'presidentContactNo'                => ['required', 'string', 'max:50'],
            'adviserRow'                        => ['required', 'array', 'min:1'],
            'adviserRow.*'                      => ['required', 'string', 'max:255'],
            'collegeDean'                       => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $officerOrgIds, true)) {
                abort(403);
            }
        }

        $facilities = array_values(array_filter($validated['facilitiesOrEquipmentToBeUsedRow'] ?? [], fn ($v) => filled($v)));
        $advisers   = array_values(array_filter($validated['adviserRow'], fn ($v) => filled($v)));

        $payload = [
            'date'                             => $validated['date'],
            'organization'                     => $validated['organization'],
            'projectActivity'                  => $validated['projectActivity'],
            'purposed'                         => $validated['purposed'],
            'dayOfTheWeek'                     => \Carbon\Carbon::parse($validated['date'])->format('l'),
            'time'                             => $validated['time'],
            'placeAndVenue'                    => $validated['placeAndVenue'],
            'facilitiesOrEquipmentToBeUsedRow' => $facilities,
            'presidentName'                    => $validated['presidentName'],
            'presidentContactNo'               => $validated['presidentContactNo'],
            'adviserRow'                       => $advisers,
            'collegeDean'                      => $validated['collegeDean'] ?? '',
        ];

        $form = Form::query()->where('route_name', 'activity-request')->firstOrFail();

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        $template = Template::query()
            ->where('form_id', $form->id)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->with(['mappings.field'])
            ->first();

        if (! $template) {
            return redirect()->route('activity-request')
                ->with('status', 'Request submitted. No active template found — document not generated yet.');
        }

        try {
            $documentGenerationService->generateFromSubmission(
                $submission->fresh(['form']),
                $template,
                null,
                $userId,
            );

            return redirect()->route('download-files')
                ->with('success', 'Activity request generated and is now available for download.');
        } catch (\Throwable $e) {
            return redirect()->route('activity-request')
                ->with('status', 'Request submitted, but document generation failed: ' . $e->getMessage());
        }
    }
}
