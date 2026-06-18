<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $validated = $request->validate([
            'organization_id'                   => ['required', 'integer', 'min:1'],
            'organization'                      => ['required', 'string', 'max:255'],
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

            // ── Waiver (NEW) ─────────────────────────────────────────────────
            'waiver_file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120', // 5 MB
            ],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $officerOrgIds, true)) {
                abort(403);
            }
        }

        // ── 1. Store the waiver file on the private disk ──────────────────────
        //
        // Stored under  storage/app/private/waivers/{year}/{random}.ext
        // NOT publicly accessible — serve to admins via downloadWaiver() below.

        $waiverPath = $request->file('waiver_file')->store(
            'waivers/' . now()->year,
            'private'
        );

        // ── 2. Build the payload (all original fields + waiver meta) ──────────

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

            // Waiver meta stored inside the JSON payload (NEW)
            'waiver_file_path'        => $waiverPath,
            'waiver_status'           => 'pending',  // admin flips to 'verified' before approval
            'waiver_reviewed_by'      => null,
            'waiver_reviewed_at'      => null,
            'waiver_rejection_reason' => null,
        ];

        // ── 3. Create the FormSubmission (unchanged) ──────────────────────────

        $form = Form::query()->where('route_name', 'activity-request')->firstOrFail();

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $payload,
        ]);

        // ── 4. Generate the document (unchanged) ─────────────────────────────

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
                ->with('success', 'Activity request submitted. Your parent/guardian waiver is pending admin review before final approval.');
        } catch (\Throwable $e) {
            return redirect()->route('activity-request')
                ->with('status', 'Request submitted, but document generation failed: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // VERIFY WAIVER  –  admin marks the uploaded waiver as authentic
    // ──────────────────────────────────────────────────────────────────────────

    public function verifyWaiver(Request $request, FormSubmission $submission): \Illuminate\Http\RedirectResponse
    {
        $payload = $submission->payload;
        $payload['waiver_status']      = 'verified';
        $payload['waiver_reviewed_by'] = $request->user()->getKey();
        $payload['waiver_reviewed_at'] = now()->toDateTimeString();

        $submission->update(['payload' => $payload]);

        return back()->with('success', 'Waiver marked as verified.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REJECT WAIVER  –  admin rejects (unsigned, unreadable, wrong document)
    // ──────────────────────────────────────────────────────────────────────────

    public function rejectWaiver(Request $request, FormSubmission $submission): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = $submission->payload;
        $payload['waiver_status']           = 'rejected';
        $payload['waiver_reviewed_by']      = $request->user()->getKey();
        $payload['waiver_reviewed_at']      = now()->toDateTimeString();
        $payload['waiver_rejection_reason'] = $request->rejection_reason ?? '';

        $submission->update(['payload' => $payload]);

        return back()->with('status', 'Waiver rejected. The student will need to re-upload.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // APPROVE  –  blocked until waiver_status is 'verified'
    // ──────────────────────────────────────────────────────────────────────────

    public function approve(FormSubmission $submission): \Illuminate\Http\RedirectResponse
    {
        $waiverStatus = $submission->payload['waiver_status'] ?? 'pending';

        if ($waiverStatus !== 'verified') {
            return back()->with(
                'status',
                'This request cannot be approved yet — the parent/guardian waiver has not been verified.'
            );
        }

        // Replace 'approved' with whatever status string your system uses
        $submission->update(['status' => 'approved']);

        return back()->with('success', 'Activity request approved.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DOWNLOAD WAIVER  –  streams the private file to the admin
    // ──────────────────────────────────────────────────────────────────────────

    public function downloadWaiver(FormSubmission $submission)
    {
        $path = $submission->payload['waiver_file_path'] ?? null;

        abort_if(! $path || ! Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path);
    }
}