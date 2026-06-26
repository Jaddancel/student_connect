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

        return view('pages.form.activity-request', [
            'title'            => 'Request for Organizational Activity',
            'isAdmin'          => $isAdmin,
            'organizations'    => $organizations,
            'presidentName'    => $presidentName,
            'presidentContact' => $presidentContact,
            'presidentsByOrg'  => $presidentsByOrg,
        ]);
    }

    /**
     * Render a print-ready parent/guardian waiver pre-filled with the supplied
     * details. The user prints this, has it signed, then uploads it on the
     * activity request form.
     */
    public function waiverDocument(Request $request)
    {
        $validated = $request->validate([
            'studentName'  => ['nullable', 'string', 'max:255'],
            'studentId'    => ['nullable', 'string', 'max:255'],
            'parentName'   => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'activityName' => ['nullable', 'string', 'max:255'],
            'activityDate' => ['nullable', 'string', 'max:255'],
            'venue'        => ['nullable', 'string', 'max:255'],
        ]);

        return view('exports.waiver-print', [
            'studentName'  => $validated['studentName']  ?? '',
            'studentId'    => $validated['studentId']    ?? '',
            'parentName'   => $validated['parentName']   ?? '',
            'relationship' => $validated['relationship'] ?? '',
            'activityName' => $validated['activityName'] ?? '',
            'activityDate' => $validated['activityDate'] ?? '',
            'venue'        => $validated['venue']        ?? '',
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
            'activityTypes'                     => ['nullable', 'array'],
            'activityTypes.*'                   => ['string'],
            'activityTypeOther'                 => ['nullable', 'string', 'max:255'],
            'areaScope'                         => ['nullable', 'string', 'max:100'],
            'areaScopeOther'                    => ['nullable', 'string', 'max:255'],
            'sponsor'                           => ['nullable', 'string', 'max:100'],
            'sponsorOther'                      => ['nullable', 'string', 'max:255'],
            'extensionServices'                 => ['nullable', 'in:yes,no'],
            'presidentName'                     => ['required', 'string', 'max:255'],
            'presidentContactNo'                => ['required', 'string', 'max:50'],
            'adviserRow'                        => ['required', 'array', 'min:1'],
            'adviserRow.*'                      => ['required', 'string', 'max:255'],
            'collegeDean'                       => ['nullable', 'string', 'max:255'],
            'parentGuardianWaiver'              => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            'activityTypes'                    => array_values(array_filter($validated['activityTypes'] ?? [], fn ($v) => filled($v))),
            'activityTypeOther'                => $validated['activityTypeOther'] ?? '',
            'areaScope'                        => $validated['areaScope'] ?? '',
            'areaScopeOther'                   => $validated['areaScopeOther'] ?? '',
            'sponsor'                          => $validated['sponsor'] ?? '',
            'sponsorOther'                     => $validated['sponsorOther'] ?? '',
            'extensionServices'                => $validated['extensionServices'] ?? '',
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

        // Store the signed parent/guardian waiver and record its path on the
        // submission so it can be appended as a separate page during generation.
        $disk = config('documents.disk', 'public');
        $waiverFile = $request->file('parentGuardianWaiver');
        $waiverFilename = Str::lower(Str::random(24)).'.'.$waiverFile->getClientOriginalExtension();
        $waiverPath = $waiverFile->storeAs(
            'activity-waivers/'.$submission->getKey(),
            $waiverFilename,
            ['disk' => $disk],
        );

        $payload['parentGuardianWaiver'] = $waiverPath;
        $submission->payload = $payload;
        $submission->save();

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
