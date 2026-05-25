<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationType;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JointStatementController extends Controller
{
    public function index(Request $request)
    {
        $user   = $request->user();
        $userId = (int) $user->getKey();

        $orgId = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId)[0] ?? null;

        $presidentRow = $orgId ? DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', $orgId)
            ->where('oo.role', 'president')
            ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
            ->first() : null;

        $presidentName = $presidentRow ? trim(implode(' ', array_filter([
            $presidentRow->first_name,
            $presidentRow->middle_name,
            $presidentRow->last_name,
        ]))) : '';

        $presidentContact = $presidentRow?->contact_number ?? '';

        $orgName = '';
        $orgCategory = '';
        if ($orgId) {
            $org = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('o.organization_id', $orgId)
                ->select(['od.name', 'o.organization_type'])
                ->first();

            $orgName = $org?->name ?? '';
            $orgCategory = $org ? OrganizationType::label((int) $org->organization_type) : '';
        }

        return view('pages.form.joint-statement', [
            'title'            => 'Joint Statement of Involvement/Commitment',
            'presidentName'    => $presidentName,
            'presidentContact' => $presidentContact,
            'orgName'          => $orgName,
            'orgCategory'      => $orgCategory,
            'orgId'            => $orgId,
        ]);
    }

    public function store(Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'organization' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'president_name' => ['required', 'string', 'max:255'],
            'president_contact' => ['required', 'string', 'max:50'],
            'president_signature' => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'adviser1_name' => ['required', 'string', 'max:255'],
            'adviser1_contact' => ['required', 'string', 'max:50'],
            'adviser2_name' => ['nullable', 'string', 'max:255'],
            'adviser2_contact' => ['nullable', 'string', 'max:50'],
            'adviser2_signature' => ['nullable', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $form = Form::query()->where('route_name', 'joint-statement')->first();

        if (! $form) {
            return back()->withErrors(['form' => 'Joint statement form is not configured.']);
        }

        $orgId = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId)[0] ?? null;

        if (! $orgId) {
            return back()->withErrors(['organization' => 'No officer organization was found for your account.']);
        }

        $signaturePath = '';
        if ($request->hasFile('president_signature') && $request->file('president_signature')->isValid()) {
            $file = $request->file('president_signature');
            $signaturePath = $file->storeAs(
                'form-submissions/joint-statement',
                (string) Str::uuid().'.'.$file->getClientOriginalExtension(),
                'public'
            );
        } else {
            return back()->withErrors(['president_signature' => 'Signature upload failed. Please try again.']);
        }

        $adviser2SignaturePath = '';
        if ($request->hasFile('adviser2_signature')) {
            if (! $request->file('adviser2_signature')->isValid()) {
                return back()->withErrors(['adviser2_signature' => 'Adviser 2 signature upload failed. Please try again.']);
            }

            $file = $request->file('adviser2_signature');
            $adviser2SignaturePath = $file->storeAs(
                'form-submissions/joint-statement',
                (string) Str::uuid().'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $payload = [
            'date' => $validated['date'],
            'organization' => $validated['organization'],
            'category' => $validated['category'],
            'presidentName' => $validated['president_name'],
            'presidentContact' => $validated['president_contact'],
            'presidentSignature' => $signaturePath,
            'adviser1Name' => $validated['adviser1_name'],
            'adviser1Contact' => $validated['adviser1_contact'],
            'adviser2Name' => $validated['adviser2_name'] ?? '',
            'adviser2Contact' => $validated['adviser2_contact'] ?? '',
            'adviser2Signature' => $adviser2SignaturePath,
            'organization_id' => $orgId,
        ];

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $orgId,
            'submitted_by' => $userId,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        $documentGenerationService->createDocumentGenerationRequest(
            $orgId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId
        );

        return redirect()->route('joint-statement')
            ->with('success', 'Joint statement submitted. Awaiting admin approval.');
    }
}
