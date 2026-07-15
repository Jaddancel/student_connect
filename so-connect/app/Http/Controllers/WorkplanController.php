<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\Workplan;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use App\Services\WorkplanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkplanController extends Controller
{
    public function review(Request $request, int $workplan_id, WorkplanService $workplanService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $workplan = Workplan::query()->with(['semester', 'organization'])->findOrFail($workplan_id);

        if (! $isAdmin) {
            $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array((int) $workplan->organization_id, $presidentOrgIds, true)) {
                abort(403);
            }
        }

        $plans = $workplanService->getApprovedPlansForWorkplan($workplan);

        $personIds = $plans->flatMap(fn ($p) => $p->persons_responsible ?? [])->unique()->filter()->values()->all();
        $personNames = [];

        if (! empty($personIds)) {
            $personNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $personIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()
                ->pluck('name', 'user_id')
                ->all();
        }

        $orgRow = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $workplan->organization_id)
            ->select(DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
            ->first();

        $presidentRow = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', $workplan->organization_id)
            ->where('oo.role', 'president')
            ->select(['p.first_name', 'p.middle_name', 'p.last_name'])
            ->first();

        $presidentName = $presidentRow ? trim(implode(' ', array_filter([
            $presidentRow->first_name,
            $presidentRow->middle_name,
            $presidentRow->last_name,
        ]))) : '';

        // Determine if a workplan request has already been submitted for this workplan.
        $requestStatus = null; // null | 'pending' | 'approved' | 'rejected'
        $generatedDocument = null;

        $workplanForm = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_WORKPLAN);
        if ($workplanForm) {
            $matchedSubmission = FormSubmission::query()
                ->where('form_id', (int) $workplanForm->getKey())
                ->where('organization_id', $workplan->organization_id)
                ->orderByDesc('submitted_at')
                ->get()
                ->first(function ($sub) use ($workplan) {
                    $p = (array) ($sub->payload ?? []);
                    return (int) ($p['semester_id'] ?? 0) === (int) $workplan->semester_id;
                });

            if ($matchedSubmission) {
                $actionRequest = ActionRequest::query()
                    ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
                    ->where('organization_id', $workplan->organization_id)
                    ->orderByDesc('requested_at')
                    ->get()
                    ->first(fn ($r) => (int) (((array) ($r->payload ?? []))['submission_id'] ?? 0) === (int) $matchedSubmission->getKey());

                if ($actionRequest) {
                    $approval = Approval::query()->where('request', $actionRequest->request_id)->first();
                    if ($approval) {
                        $requestStatus = $approval->is_rejected ? 'rejected' : 'approved';
                        if ($requestStatus === 'approved') {
                            $generatedDocument = GeneratedDocument::query()
                                ->where('request_id', $actionRequest->request_id)
                                ->first();
                        }
                    } else {
                        $requestStatus = 'pending';
                    }
                }
            }
        }

        return view('pages.form.workplan', [
            'title' => 'Workplan',
            'workplan' => $workplan,
            'semester' => $workplan->semester,
            'plans' => $plans,
            'personNames' => $personNames,
            'orgName' => $orgRow?->name ?? 'Unknown Organization',
            'presidentName' => $presidentName,
            'isAdmin' => $isAdmin,
            'requestStatus' => $requestStatus,
            'generatedDocument' => $generatedDocument,
        ]);
    }

    public function generatePdf(
        Request $request,
        int $workplan_id,
        DocumentGenerationService $docService,
        WorkplanService $workplanService,
    ) {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $workplan = Workplan::query()->with('semester')->findOrFail($workplan_id);

        if ($workplan->status !== 'finalized') {
            return back()->withErrors(['workplan' => 'Only finalized workplans can generate a PDF.']);
        }

        if (! $isAdmin) {
            $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array((int) $workplan->organization_id, $presidentOrgIds, true)) {
                abort(403);
            }
        }

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'advisername'      => ['nullable', 'string', 'max:255'],
            'signature'        => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $plans = $workplanService->getApprovedPlansForWorkplan($workplan);

        $missingFields = [];
        foreach ($plans as $plan) {
            if (empty(trim((string) ($plan->resources_needed ?? '')))) {
                $missingFields[] = "Plan \"{$plan->title}\" is missing required field: Resources Needed.";
            }
            if (empty($plan->persons_responsible ?? [])) {
                $missingFields[] = "Plan \"{$plan->title}\" is missing required field: Persons Responsible.";
            }
        }

        if (! empty($missingFields)) {
            return redirect()->route('workplan.review', $workplan_id)
                ->withErrors(['workplan' => implode(' ', $missingFields)]);
        }

        $personIds = $plans->flatMap(fn ($p) => $p->persons_responsible ?? [])->unique()->filter()->values()->all();
        $personNames = [];

        if (! empty($personIds)) {
            $personNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $personIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()
                ->pluck('name', 'user_id')
                ->all();
        }

        $orgRow = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $workplan->organization_id)
            ->select(DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
            ->first();

        $activities = $plans->map(function ($plan) use ($personNames) {
            $names = collect($plan->persons_responsible ?? [])
                ->map(fn ($id) => $personNames[$id] ?? null)
                ->filter()
                ->implode(', ');

            return [
                'title'  => (string) $plan->title,
                'target' => \Illuminate\Support\Carbon::parse($plan->target_date)->format('M d, Y'),
                'resources' => (string) ($plan->resources_needed ?? ''),
                'people' => $names,
            ];
        })->values()->all();

        $form = \App\Forms\SystemFunction::formOrFail(\App\Forms\SystemFunction::NEW_WORKPLAN);

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $payload = [
            'organization_id'  => (int) $workplan->organization_id,
            'semester_id'      => (int) $workplan->semester_id,
            'organization'     => $orgRow?->name ?? 'Unknown Organization',
            'schoolyear'       => $workplan->semester->name,
            'activities'       => array_column($activities, 'title'),
            'target'           => array_column($activities, 'target'),
            'people'           => array_column($activities, 'people'),
            'resources'        => array_column($activities, 'resources'),
            'name'             => $validated['name'],
            'advisername'      => $validated['advisername'] ?? '',
            'signature'        => '',
        ];

        foreach (['signature'] as $sigField) {
            if ($request->hasFile($sigField)) {
                $file = $request->file($sigField);
                $path = $file->storeAs(
                    $sigDir,
                    Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
                $payload[$sigField] = $path;
            }
        }

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => (int) $workplan->organization_id,
            'submitted_by' => $userId,
            'submitted_at' => now(),
            'payload' => $payload,
        ]);

        $docService->createDocumentGenerationRequest(
            (int) $workplan->organization_id,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()->route('workplan.review', $workplan_id)
            ->with('success', 'Workplan request submitted and is pending admin review.');
    }

    public function downloadPdf(Request $request, int $workplan_id)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $workplan = Workplan::query()->with('semester')->findOrFail($workplan_id);

        if (! $isAdmin) {
            $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array((int) $workplan->organization_id, $presidentOrgIds, true)) {
                abort(403);
            }
        }

        $workplanForm = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_WORKPLAN);
        if (! $workplanForm) {
            abort(404);
        }

        $matchedSubmission = FormSubmission::query()
            ->where('form_id', (int) $workplanForm->getKey())
            ->where('organization_id', $workplan->organization_id)
            ->orderByDesc('submitted_at')
            ->get()
            ->first(function ($sub) use ($workplan) {
                $p = (array) ($sub->payload ?? []);
                return (int) ($p['semester_id'] ?? 0) === (int) $workplan->semester_id;
            });

        if (! $matchedSubmission) {
            abort(404);
        }

        $actionRequest = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('organization_id', $workplan->organization_id)
            ->orderByDesc('requested_at')
            ->get()
            ->first(fn ($r) => (int) (((array) ($r->payload ?? []))['submission_id'] ?? 0) === (int) $matchedSubmission->getKey());

        if (! $actionRequest) {
            abort(404);
        }

        $approval = Approval::query()->where('request', $actionRequest->request_id)->first();
        if (! $approval || $approval->is_rejected) {
            abort(403);
        }

        $generatedDocument = GeneratedDocument::query()
            ->where('request_id', $actionRequest->request_id)
            ->first();

        if (! $generatedDocument) {
            return back()->withErrors(['workplan' => 'The generated document is not yet available.']);
        }

        $path = (string) ($generatedDocument->pdf_path ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return back()->withErrors(['workplan' => 'The PDF file could not be found.']);
        }

        return Storage::disk('public')->download($path, 'Workplan.pdf');
    }
}
