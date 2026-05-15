<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\RequestApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'organization'        => ['required', 'string', 'max:255'],
            'name_of_president'   => ['required', 'string', 'max:255'],
            'name_of_adviser_s'   => ['nullable', 'string', 'max:255'],
            'date'                => ['nullable', 'date'],
            'freshman'            => ['nullable', 'integer', 'min:0'],
            'sophomore'           => ['nullable', 'integer', 'min:0'],
            'junior'              => ['nullable', 'integer', 'min:0'],
            'total'               => ['nullable', 'integer', 'min:0'],
            'objectives'          => ['nullable', 'string'],
            'workplan'            => ['nullable', 'string'],
            'organization_id'     => ['nullable', 'integer'],
            'signature_president' => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'name_of_adviser_1'   => ['nullable', 'string', 'max:255'],
            'signature_1'         => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'name_of_adviser_2'   => ['nullable', 'string', 'max:255'],
            'signature_2'         => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'signature_3'         => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'chair'               => ['nullable', 'string', 'max:255'],
            'signature_4'         => ['nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
            'director'            => ['nullable', 'string', 'max:255'],
        ]);

        $form = Form::query()->where('route_name', 'organization-recognition')->firstOrFail();

        $organizationId = $isAdmin ? null : ((int) ($validated['organization_id'] ?? 0) ?: null);

        $c1 = $isAdmin && $request->boolean('c1');
        $c2 = $request->boolean('c2');

        $sigDir = 'form-signatures/'.now()->format('Y/m');

        $payload = [
            'c1'                  => $c1,
            'c2'                  => $c2,
            'organization'        => $validated['organization'],
            'name_of_president'   => $validated['name_of_president'],
            'name_of_adviser_s'   => $validated['name_of_adviser_s'] ?? '',
            'date'                => $validated['date'] ?? '',
            'freshman'            => $validated['freshman'] ?? '',
            'sophomore'           => $validated['sophomore'] ?? '',
            'junior'              => $validated['junior'] ?? '',
            'total'               => $validated['total'] ?? '',
            'objectives'          => $validated['objectives'] ?? '',
            'workplan'            => $validated['workplan'] ?? '',
            'name_of_adviser_1'   => $validated['name_of_adviser_1'] ?? '',
            'name_of_adviser_2'   => $validated['name_of_adviser_2'] ?? '',
            'chair'               => $isAdmin ? ($validated['chair'] ?? '') : '',
            'director'            => $isAdmin ? ($validated['director'] ?? '') : '',
            'create_organization' => $c1,
        ];

        foreach (['signature_president', 'signature_1', 'signature_2', 'signature_3', 'signature_4'] as $sigField) {
            if ($sigField === 'signature_3' || $sigField === 'signature_4') {
                if (! $isAdmin) {
                    $payload[$sigField] = '';
                    continue;
                }
            }

            if ($request->hasFile($sigField)) {
                $file = $request->file($sigField);
                $path = $file->storeAs(
                    $sigDir,
                    Str::lower(Str::random(16)).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
                $payload[$sigField] = $path;
            } else {
                $payload[$sigField] = '';
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

        return redirect()->route('organization-recognition')
            ->with('success', $isAdmin
                ? 'Form submitted and auto-approved. The document will be available in Download Files.'
                : 'Your application has been submitted and is pending admin approval.'
            );
    }
}
