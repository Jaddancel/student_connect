<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\RequestApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkplanController extends Controller
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
                ->where('oo.role', 'president')
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

        return view('pages.form.workplan', [
            'title' => 'Workplan for President',
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
            'organization'              => ['required', 'string', 'max:255'],
            'school_year'               => ['required', 'string', 'max:50'],
            'organization_id'           => ['nullable', 'integer'],
            'activities'                => ['nullable', 'array', 'max:50'],
            'activities.*.title'        => ['nullable', 'string', 'max:255'],
            'activities.*.target_date'  => ['nullable', 'string', 'max:100'],
            'activities.*.resources'    => ['nullable', 'string', 'max:500'],
            'activities.*.people'       => ['nullable', 'string', 'max:500'],
            'name'                      => ['required', 'string', 'max:255'],
            'adviser_name'              => ['nullable', 'string', 'max:255'],
            'signature'                 => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'adviser_signature'         => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $form = Form::query()->where('route_name', 'workplan')->firstOrFail();

        $organizationId = $isAdmin ? null : ((int) ($validated['organization_id'] ?? 0) ?: null);

        $activities = collect($validated['activities'] ?? [])
            ->map(fn ($row) => [
                'title'       => (string) ($row['title'] ?? ''),
                'target_date' => (string) ($row['target_date'] ?? ''),
                'resources'   => (string) ($row['resources'] ?? ''),
                'people'      => (string) ($row['people'] ?? ''),
            ])
            ->filter(fn ($row) => $row['title'] !== '' || $row['target_date'] !== '' || $row['resources'] !== '' || $row['people'] !== '')
            ->values()
            ->all();

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $payload = [
            'organization'  => $validated['organization'],
            'school_year'   => $validated['school_year'],
            'activities'    => $activities,
            'name'          => $validated['name'],
            'adviser_name'  => $validated['adviser_name'] ?? '',
            'signature'     => '',
            'adviser_signature' => '',
        ];

        foreach (['signature', 'adviser_signature'] as $sigField) {
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

        return redirect()->route('workplan')
            ->with('success', $isAdmin
                ? 'Workplan submitted and auto-approved. The document will be available in Download Files.'
                : 'Your workplan has been submitted and is pending admin approval.'
            );
    }
}
