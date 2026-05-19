<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
use App\Models\Template;
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

        $orgIds = $organizations->pluck('organization_id')->toArray();

        $events = DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->whereIn('e.organization', $orgIds)
            ->orderByDesc('ed.start_time')
            ->select([
                'e.event_id',
                'e.organization as org_id',
                'ed.name as title',
                DB::raw('DATE(ed.start_time) as date'),
            ])
            ->get();

        return view('pages.form.accomplishment-report', [
            'title'         => 'Accomplishment Report',
            'isAdmin'       => $isAdmin,
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
        $isAdmin = (int) $user->user_type === 2;

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
            'adviserName'     => ['nullable', 'string', 'max:255'],
            'has_rewards'         => ['nullable', 'boolean'],
            'is_individual'       => ['required_if:has_rewards,1', 'in:yes,no'],
            'area_scope_of_award' => ['required_if:has_rewards,1', 'in:Local,Provincial,Regional,International'],
            'minutes_of_meeting'   => ['nullable', 'integer', 'min:0'],
            'summary_of_expenses'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $isAdmin) {
            $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $officerOrgIds, true)) {
                abort(403);
            }
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
            'adviserName'  => $validated['adviserName'] ?? '',
            'has_rewards'         => (bool) ($validated['has_rewards'] ?? false),
            'is_individual'       => $validated['is_individual'] ?? null,
            'area_scope_of_award' => $validated['area_scope_of_award'] ?? null,
            'minutes_of_meeting'  => isset($validated['minutes_of_meeting']) ? (int) $validated['minutes_of_meeting'] : null,
            'summary_of_expenses' => isset($validated['summary_of_expenses']) ? (float) $validated['summary_of_expenses'] : null,
        ];

        foreach (['photos', 'signature'] as $fileField) {
            if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                $file = $request->file($fileField);
                $path = $file->storeAs(
                    $sigDir,
                    Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
                $payload[$fileField] = $path;
            } else {
                $payload[$fileField] = '';
            }
        }

        $form = Form::query()->where('route_name', 'accomplishment-report')->firstOrFail();

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
            return redirect()->route('accomplishment-report')
                ->with('status', 'Report submitted. No active template found — document not generated yet.');
        }

        try {
            $documentGenerationService->generateFromSubmission(
                $submission->fresh(['form']),
                $template,
                null,
                $userId,
            );

            return redirect()->route('download-files')
                ->with('success', 'Accomplishment report generated and is now available for download.');
        } catch (\Throwable $e) {
            return redirect()->route('accomplishment-report')
                ->with('status', 'Report submitted, but document generation failed: '.$e->getMessage());
        }
    }
}
